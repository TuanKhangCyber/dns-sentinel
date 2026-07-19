<?php

namespace App\Http\Controllers;

use App\Models\ReconHistory;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\MembershipService;
use App\Services\Recon\EmailSecurityService;
use App\Services\Recon\NmapService;
use App\Services\Recon\SecurityHeadersService;
use App\Services\Recon\SslCertificateService;
use App\Services\Recon\SubdomainScannerService;
use App\Services\Recon\TargetGuard;
use App\Services\Recon\TechFingerprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReconController extends Controller
{
    public function __construct(
        private readonly SecurityHeadersService $securityHeaders,
        private readonly EmailSecurityService $emailSecurity,
        private readonly SslCertificateService $sslCertificate,
        private readonly SubdomainScannerService $subdomains,
        private readonly TechFingerprintService $techFingerprint,
        private readonly NmapService $nmap,
        private readonly TargetGuard $targetGuard,
        private readonly FeatureAccessService $features,
        private readonly CreditService $credits,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate(['target' => $this->targetRules()]);
        $target = $this->normalizeSafeDomain($validated['target']);
        $this->features->authorize($request->user(), 'recon_history');
        $history = ReconHistory::create([
            'user_id' => $request->user()->id,
            'target' => $target,
            'status' => 'running',
            'results' => [],
        ]);

        return response()->json(['history_id' => $history->id, 'target' => $history->target], 201);
    }

    public function scan(Request $request, string $service): JsonResponse
    {
        $allowed = ['security_headers', 'email_security', 'ssl_certificate', 'subdomains', 'tech_fingerprint', 'nmap'];
        validator(['service' => $service], ['service' => ['required', Rule::in($allowed)]])->validate();
        $validated = $request->validate([
            'target' => $this->targetRules(),
            'history_id' => [
                'required', 'integer',
                Rule::exists('recon_histories', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
        ]);
        $target = $this->normalizeSafeDomain($validated['target']);
        $history = ReconHistory::whereKey($validated['history_id'])
            ->where('user_id', $request->user()->id)
            ->where('target', $target)
            ->firstOrFail();

        $featureCode = match ($service) {
            'security_headers' => 'security_headers', 'email_security' => 'email_security',
            'ssl_certificate' => 'ssl_analysis', 'subdomains' => 'subdomain_scan',
            'tech_fingerprint' => 'technology_fingerprint', 'nmap' => 'nmap_scan',
        };
        $access = $this->features->authorize($request->user(), $featureCode);
        $this->credits->charge($request->user(), $featureCode, $access['cost'], ReconHistory::class, $history->id);

        $result = match ($service) {
            'security_headers' => $this->securityHeaders->lookup($target),
            'email_security' => $this->emailSecurity->lookup($target),
            'ssl_certificate' => $this->sslCertificate->lookup($target),
            'subdomains' => $this->subdomains->lookup($target),
            'tech_fingerprint' => $this->techFingerprint->lookup($target),
            'nmap' => $this->nmap->lookup($target),
        };

        Cache::lock('recon-history:'.$history->id, 10)->block(5, function () use ($history, $service, $result, $allowed) {
            $history->refresh();
            $results = $history->results ?? [];
            $results[$service] = $result;
            $scores = collect($results)->pluck('score')->filter(fn ($score) => is_numeric($score));
            $statuses = collect($results)->pluck('status');
            $completed = collect($allowed)->every(fn ($name) => array_key_exists($name, $results));
            $riskStatus = $statuses->contains('danger') ? 'danger'
                : ($statuses->contains('warning') ? 'warning' : ($statuses->contains('error') ? 'error' : 'safe'));
            $history->update([
                'results' => $results,
                'overall_score' => $scores->isEmpty() ? null : (int) round($scores->average()),
                'status' => $completed ? $riskStatus : 'running',
                'completed_at' => $completed ? now() : null,
            ]);
        });

        return response()->json(['history_id' => $history->id, 'result' => $result]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->features->authorize($request->user(), 'recon_history');
        $retention = app(MembershipService::class)->effectivePlan($request->user())->history_retention_days;
        $histories = ReconHistory::where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDays($retention))
            ->latest()
            ->limit(30)
            ->get(['id', 'target', 'status', 'overall_score', 'created_at', 'completed_at']);

        return response()->json(['histories' => $histories]);
    }

    public function show(Request $request, ReconHistory $history): JsonResponse
    {
        $this->features->authorize($request->user(), 'recon_history');
        abort_unless($history->user_id === $request->user()->id, 404);

        return response()->json(['history' => $history]);
    }

    private function targetRules(): array
    {
        return [
            'required', 'string', 'max:253',
            function (string $attribute, mixed $value, \Closure $fail) {
                try {
                    $this->targetGuard->normalize((string) $value, false);
                } catch (InvalidArgumentException $exception) {
                    $fail($exception->getMessage());
                }
            },
        ];
    }

    private function normalizeSafeDomain(string $target): string
    {
        try {
            $domain = $this->targetGuard->normalize($target, false);
            $this->targetGuard->assertSafeDnsTarget($domain);

            return $domain;
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target' => $exception->getMessage()]);
        }
    }
}
