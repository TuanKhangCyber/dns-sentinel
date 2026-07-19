<?php

namespace App\Services\Recon;

use App\Exceptions\Recon\ReconLookupException;
use App\Services\Recon\Concerns\BuildsReconResults;
use Illuminate\Support\Facades\Http;

class SecurityHeadersService
{
    use BuildsReconResults;

    public function __construct(private readonly TargetGuard $guard) {}

    public function lookup(string $domain): array
    {
        return $this->cached('security_headers', $domain, config('recon.cache_minutes'), function () use ($domain) {
            $ips = $this->guard->assertPublicDomain($domain);
            $response = Http::withHeaders([
                'User-Agent' => 'DNS-Recon-Security/1.0',
                'Range' => 'bytes=0-65535',
            ])->connectTimeout(4)
                ->timeout(config('recon.timeout_seconds'))
                ->withOptions($this->guard->pinnedHttpsOptions($domain, $ips, 65536))
                ->get('https://'.$domain);

            if (! $response->successful()) {
                throw new ReconLookupException(__('ui.lookup_failed', ['status' => $response->status()]));
            }

            $definitions = [
                'Strict-Transport-Security' => ['weight' => 20, 'risk' => 'high'],
                'Content-Security-Policy' => ['weight' => 25, 'risk' => 'high'],
                'X-Frame-Options' => ['weight' => 15, 'risk' => 'medium'],
                'X-Content-Type-Options' => ['weight' => 15, 'risk' => 'medium'],
                'Referrer-Policy' => ['weight' => 10, 'risk' => 'low'],
                'Permissions-Policy' => ['weight' => 15, 'risk' => 'medium'],
            ];

            $headers = [];
            $score = 0;
            foreach ($definitions as $name => $definition) {
                $value = $response->header($name);
                $present = is_string($value) && trim($value) !== '';
                if ($present) {
                    $score += $definition['weight'];
                }
                $headers[] = [
                    'name' => $name,
                    'present' => $present,
                    'value' => $present ? $value : null,
                    'risk' => $present ? 'safe' : $definition['risk'],
                ];
            }

            $status = $score >= 85 ? 'safe' : ($score >= 55 ? 'warning' : 'danger');

            return $this->result('security_headers', $domain, $status, $score, [
                'url' => 'https://'.$domain,
                'http_status' => $response->status(),
                'headers' => $headers,
            ]);
        });
    }
}
