<?php

namespace App\Services\Recon;

use App\Exceptions\Recon\ReconLookupException;
use App\Services\Recon\Concerns\BuildsReconResults;
use Illuminate\Support\Facades\Http;

class SubdomainScannerService
{
    use BuildsReconResults;

    public function __construct(private readonly TargetGuard $guard) {}

    public function lookup(string $domain): array
    {
        return $this->cached('subdomains', $domain, config('recon.subdomain_cache_minutes'), function () use ($domain) {
            $this->guard->assertSafeDnsTarget($domain);
            $response = Http::acceptJson()
                ->withHeaders(['User-Agent' => 'DNS-Recon-Security/1.0'])
                ->connectTimeout(5)
                ->timeout(max(15, config('recon.timeout_seconds')))
                ->withOptions([
                    'allow_redirects' => false,
                    ...$this->guard->responseLimitOptions((int) config('recon.http_response_max_bytes')),
                ])
                ->retry([300, 900], throw: false)
                ->get(config('recon.crt_url'), ['q' => '%.'.$domain, 'output' => 'json']);

            if (strlen($response->body()) > (int) config('recon.http_response_max_bytes')) {
                throw new ReconLookupException(__('ui.response_too_large'));
            }
            if (! $response->successful() || ! is_array($response->json())) {
                throw new ReconLookupException(__('ui.lookup_failed', ['status' => $response->status()]));
            }

            $names = [];
            foreach ($response->json() as $certificate) {
                foreach (preg_split('/\R/', $certificate['name_value'] ?? '') as $name) {
                    $name = strtolower(ltrim(trim($name), '*.'));
                    if ($name !== $domain && str_ends_with($name, '.'.$domain)
                        && filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                        $names[] = $name;
                    }
                }
            }
            $names = array_values(array_unique($names));
            sort($names);
            $discoveredCount = count($names);
            $names = array_slice($names, 0, config('recon.max_subdomains'));

            $subdomains = [];
            foreach ($names as $name) {
                $ips = [];
                foreach ([DNS_A, DNS_AAAA] as $type) {
                    foreach ($this->guard->dnsRecords($name, $type) as $record) {
                        $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
                    }
                }
                $ips = array_values(array_unique(array_filter($ips)));
                $subdomains[] = ['name' => $name, 'resolves' => $ips !== [], 'ips' => $ips];
            }

            $resolved = collect($subdomains)->where('resolves', true)->count();
            $warnings = $discoveredCount > count($names) ? ['subdomain_results_truncated'] : [];

            return $this->result('subdomains', $domain, 'safe', null, [
                'discovered_count' => $discoveredCount,
                'scanned_count' => count($subdomains),
                'resolved_count' => $resolved,
                'subdomains' => $subdomains,
            ], $warnings);
        });
    }
}
