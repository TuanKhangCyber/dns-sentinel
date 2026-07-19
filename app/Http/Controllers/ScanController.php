<?php

namespace App\Http\Controllers;

use App\Exceptions\FeatureAccessException;
use App\Jobs\RunNmapScanJob;
use App\Jobs\RunVulnerabilityScanJob;
use App\Models\Scan;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\Scanner\ScanProfileService;
use App\Services\Scanner\TargetValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ScanController extends Controller
{
    public function __construct(
        private readonly ScanProfileService $profiles,
        private readonly TargetValidationService $targets,
        private readonly FeatureAccessService $features,
        private readonly CreditService $credits,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'max:253'], 'profile' => ['required', 'string', 'max:50'],
            'speed' => ['nullable', 'string'], 'timeout' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'port_range' => ['nullable', 'string', 'max:1000'], 'scheduled_at' => ['nullable', 'date', 'after:now'],
            'authorization_confirmed' => ['accepted'],
        ]);
        if (! RateLimiter::attempt('scanner:user:'.$request->user()->id, config('scanner.rate_limit_per_hour'), fn () => true, 3600)) {
            return $this->response(null, false, __('scanner.errors.rate_limited'), 429);
        }

        try {
            $target = $this->targets->validate($validated['target']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target' => $exception->getMessage()]);
        }
        try {
            $profile = $this->profiles->get($validated['profile']);
            $options = $this->profiles->normalizeOptions($profile, $validated);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['profile' => $exception->getMessage()]);
        }
        $featureCode = $profile['scanner_type'] === 'vulnerability' ? 'vulnerability_scan' : 'nmap_scan';
        try {
            $access = $this->features->authorize($request->user(), $featureCode);
        } catch (FeatureAccessException $exception) {
            return $this->response(null, false, $exception->getMessage(), $exception->httpStatus, ['error_code' => $exception->errorCode]);
        }

        $scheduledAt = isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;
        $submissionKey = $this->submissionKey($request->user()->id, 'store', [
            $target['target'], $profile['key'], $options, $scheduledAt?->toIso8601String(),
        ]);
        if (! Cache::add($submissionKey, true, now()->addSeconds((int) config('scanner.submission_window_seconds', 10)))) {
            return $this->response(null, false, __('scanner.errors.duplicate_submission'), 409);
        }

        $scan = Scan::create([
            'user_id' => $request->user()->id, 'target' => $target['target'], 'target_type' => $target['target_type'],
            'profile' => $profile['key'], 'scanner_type' => $profile['scanner_type'], 'status' => 'queued', 'stage' => 'queued',
            'scheduled_at' => $scheduledAt, 'authorization_confirmed_at' => now(),
            'options' => [...$options, 'host_count' => $target['host_count'], 'requested_ip' => $request->ip()],
        ]);
        try {
            $charge = $this->credits->charge($request->user(), $featureCode, $access['cost'], Scan::class, $scan->id);
            $scan->update(['credit_transaction_id' => $charge?->id]);
        } catch (FeatureAccessException $exception) {
            $scan->delete();
            Cache::forget($submissionKey);

            return $this->response(null, false, $exception->getMessage(), $exception->httpStatus, ['error_code' => $exception->errorCode]);
        }
        if ($failure = $this->dispatchOrFail($scan, $submissionKey)) {
            return $failure;
        }
        Log::notice('Authorized scan created.', ['scan_id' => $scan->id, 'user_id' => $scan->user_id, 'target' => $scan->target, 'profile' => $scan->profile]);

        return $this->response($scan, true, null, 202);
    }

    public function status(Scan $scan): JsonResponse
    {
        Gate::authorize('view', $scan);
        $this->features->authorize(request()->user(), 'scan_history');

        return $this->response($scan->fresh());
    }

    public function results(Request $request, Scan $scan): JsonResponse
    {
        Gate::authorize('view', $scan);
        $this->features->authorize($request->user(), 'scan_history');
        $hosts = $scan->hosts()->with('ports')->paginate(min(50, max(1, $request->integer('per_page', 25))));
        $findings = $scan->findings()->with(['host:id,ip_address,hostname', 'port:id,port,protocol'])
            ->when($request->filled('severity'), fn ($query) => $query->where('severity', $request->string('severity')))
            ->paginate(50, ['*'], 'findings_page');

        return $this->response($scan, true, null, 200, ['hosts' => $hosts, 'findings' => $findings]);
    }

    public function cancel(Scan $scan): JsonResponse
    {
        Gate::authorize('cancel', $scan);
        $requestedAt = now();
        $updated = Scan::query()->whereKey($scan->id)
            ->whereNotIn('status', Scan::TERMINAL_STATUSES)
            ->update(['cancel_requested_at' => $requestedAt]);
        if ($updated === 0) {
            return $this->response($scan->fresh(), false, __('scanner.errors.cancel_terminal'), 409);
        }

        Scan::query()->whereKey($scan->id)->where('status', 'queued')
            ->where('cancel_requested_at', $requestedAt)
            ->update(['status' => 'cancelled', 'stage' => 'cancelled', 'completed_at' => now()]);
        $fresh = $scan->fresh();
        if ($fresh->status === 'cancelled') {
            $this->credits->refund($fresh->creditTransaction, __('platform.credit_refund_cancelled'));
        }
        Log::notice('Authorized scan cancellation requested.', ['scan_id' => $scan->id, 'user_id' => $scan->user_id, 'target' => $scan->target]);

        return $this->response($fresh);
    }

    public function destroy(Scan $scan): JsonResponse
    {
        Gate::authorize('delete', $scan);
        if (! $scan->isTerminal()) {
            return $this->response($scan, false, __('scanner.errors.delete_running'), 409);
        }
        $id = $scan->id;
        Log::notice('Authorized scan deleted.', ['scan_id' => $id, 'user_id' => $scan->user_id, 'target' => $scan->target, 'status' => $scan->status]);
        $scan->delete();

        return response()->json(['success' => true, 'scan_id' => $id, 'status' => 'deleted', 'progress' => 100, 'stage' => 'deleted', 'data' => [], 'warnings' => [], 'error' => null, 'created_at' => null, 'updated_at' => now()->toIso8601String()]);
    }

    public function rerun(Request $request, Scan $scan): JsonResponse
    {
        Gate::authorize('view', $scan);
        $request->validate(['authorization_confirmed' => ['accepted']]);
        if (! $scan->isTerminal()) {
            return $this->response($scan, false, __('scanner.errors.rerun_running'), 409);
        }
        if (! RateLimiter::attempt('scanner:user:'.$request->user()->id, config('scanner.rate_limit_per_hour'), fn () => true, 3600)) {
            return $this->response($scan, false, __('scanner.errors.rate_limited'), 429);
        }
        try {
            $target = $this->targets->validate($scan->target);
            $profile = $this->profiles->get($scan->profile);
            $options = $this->profiles->normalizeOptions($profile, $scan->options ?? []);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target' => $exception->getMessage()]);
        }
        $featureCode = $profile['scanner_type'] === 'vulnerability' ? 'vulnerability_scan' : 'nmap_scan';
        try {
            $access = $this->features->authorize($request->user(), $featureCode);
        } catch (FeatureAccessException $exception) {
            return $this->response($scan, false, $exception->getMessage(), $exception->httpStatus, ['error_code' => $exception->errorCode]);
        }
        $submissionKey = $this->submissionKey($request->user()->id, 'rerun', [$scan->id]);
        if (! Cache::add($submissionKey, true, now()->addSeconds((int) config('scanner.submission_window_seconds', 10)))) {
            return $this->response($scan, false, __('scanner.errors.duplicate_submission'), 409);
        }
        $copy = $scan->replicate(['credit_transaction_id', 'status', 'stage', 'progress', 'started_at', 'completed_at', 'cancel_requested_at', 'duration_seconds', 'error_message', 'summary']);
        $options['host_count'] = $target['host_count'];
        $options['requested_ip'] = $request->ip();
        $copy->fill([
            'target' => $target['target'], 'target_type' => $target['target_type'],
            'profile' => $profile['key'], 'scanner_type' => $profile['scanner_type'],
            'status' => 'queued', 'stage' => 'queued', 'progress' => 0,
            'authorization_confirmed_at' => now(), 'scheduled_at' => null,
            'options' => $options,
        ]);
        $copy->save();
        try {
            $charge = $this->credits->charge($request->user(), $featureCode, $access['cost'], Scan::class, $copy->id);
            $copy->update(['credit_transaction_id' => $charge?->id]);
        } catch (FeatureAccessException $exception) {
            $copy->delete();
            Cache::forget($submissionKey);

            return $this->response($scan, false, $exception->getMessage(), $exception->httpStatus, ['error_code' => $exception->errorCode]);
        }
        if ($failure = $this->dispatchOrFail($copy, $submissionKey)) {
            return $failure;
        }
        Log::notice('Authorized scan rerun created.', ['source_scan_id' => $scan->id, 'scan_id' => $copy->id, 'user_id' => $copy->user_id, 'target' => $copy->target]);

        return $this->response($copy, true, null, 202);
    }

    private function dispatch(Scan $scan): void
    {
        $job = match ($scan->scanner_type) {
            'nmap' => new RunNmapScanJob($scan->id),
            'vulnerability' => new RunVulnerabilityScanJob($scan->id),
            default => throw ValidationException::withMessages(['profile' => __('scanner.errors.scanner_unavailable')]),
        };
        if ($scan->scheduled_at?->isFuture()) {
            $job->delay($scan->scheduled_at);
        }
        Bus::dispatch($job);
    }

    private function dispatchOrFail(Scan $scan, string $submissionKey): ?JsonResponse
    {
        try {
            $this->dispatch($scan);

            return null;
        } catch (Throwable $exception) {
            Cache::forget($submissionKey);
            $scan->update([
                'status' => 'failed', 'stage' => 'failed', 'completed_at' => now(),
                'error_message' => __('scanner.errors.queue_unavailable'),
            ]);
            $this->credits->refund($scan->creditTransaction, __('platform.credit_refund_dispatch'));
            Log::error('Scanner job dispatch failed.', [
                'scan_id' => $scan->id, 'exception' => $exception::class,
            ]);

            return $this->response($scan->fresh(), false, __('scanner.errors.queue_unavailable'), 503);
        }
    }

    private function submissionKey(int $userId, string $action, array $payload): string
    {
        return 'scanner:submission:'.$userId.':'.$action.':'.hash('sha256', serialize($payload));
    }

    private function response(?Scan $scan, bool $success = true, ?string $error = null, int $code = 200, array $data = []): JsonResponse
    {
        if ($error === null && $scan?->status === 'failed') {
            $error = $scan->error_message ?: __('scanner.errors.job_failed');
        }

        return response()->json([
            'success' => $success, 'scan_id' => $scan?->id, 'status' => $scan?->status,
            'progress' => $scan?->progress ?? 0, 'stage' => $scan?->stage,
            'data' => $scan ? ['summary' => $scan->summary, ...$data] : $data,
            'error_code' => $data['error_code'] ?? null,
            'warnings' => [], 'error' => $error,
            'created_at' => $scan?->created_at?->toIso8601String(), 'updated_at' => $scan?->updated_at?->toIso8601String(),
        ], $code);
    }
}
