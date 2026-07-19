<?php

namespace App\Services\Recon;

use App\Services\Recon\Concerns\BuildsReconResults;

class EmailSecurityService
{
    use BuildsReconResults;

    public function __construct(private readonly TargetGuard $guard) {}

    public function lookup(string $domain): array
    {
        return $this->cached('email_security', $domain, config('recon.cache_minutes'), function () use ($domain) {
            $this->guard->assertSafeDnsTarget($domain);
            $spf = $this->txtRecords($domain, 'v=spf1');
            $dmarc = $this->txtRecords('_dmarc.'.$domain, 'v=DMARC1');
            $dkim = [];
            foreach (config('recon.dkim_selectors') as $selector) {
                $records = $this->txtRecords($selector.'._domainkey.'.$domain, 'v=DKIM1');
                $dkim[] = ['selector' => $selector, 'found' => $records !== [], 'records' => $records];
            }

            $warnings = [];
            $score = 0;
            if ($spf !== []) {
                $score += 30;
                if (collect($spf)->contains(fn ($record) => str_contains($record, '+all'))) {
                    $warnings[] = 'spf_allows_every_sender';
                    $score -= 15;
                }
            } else {
                $warnings[] = 'spf_missing';
            }

            $dmarcPolicy = $this->tagValue($dmarc[0] ?? '', 'p');
            if ($dmarc !== []) {
                $score += $dmarcPolicy === 'reject' ? 40 : ($dmarcPolicy === 'quarantine' ? 30 : 15);
                if ($dmarcPolicy === 'none' || $dmarcPolicy === null) {
                    $warnings[] = 'dmarc_policy_weak';
                }
            } else {
                $warnings[] = 'dmarc_missing';
            }

            $foundDkim = collect($dkim)->contains('found', true);
            if ($foundDkim) {
                $score += 30;
            } else {
                $warnings[] = 'dkim_not_found_for_common_selectors';
            }

            $score = max(0, min(100, $score));
            $status = $score >= 85 ? 'safe' : ($score >= 55 ? 'warning' : 'danger');

            return $this->result('email_security', $domain, $status, $score, [
                'spf' => ['found' => $spf !== [], 'records' => $spf],
                'dmarc' => ['found' => $dmarc !== [], 'policy' => $dmarcPolicy, 'records' => $dmarc],
                'dkim' => $dkim,
            ], $warnings);
        });
    }

    private function txtRecords(string $hostname, string $prefix): array
    {
        $values = [];
        foreach ($this->guard->dnsRecords($hostname, DNS_TXT) as $record) {
            $value = $record['txt'] ?? implode('', $record['entries'] ?? []);
            if (str_starts_with(strtoupper($value), strtoupper($prefix))) {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function tagValue(string $record, string $tag): ?string
    {
        if (preg_match('/(?:^|;)\s*'.preg_quote($tag, '/').'\s*=\s*([^;\s]+)/i', $record, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }
}
