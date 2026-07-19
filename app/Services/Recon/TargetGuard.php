<?php

namespace App\Services\Recon;

use App\Exceptions\Recon\ReconLookupException;
use InvalidArgumentException;

class TargetGuard
{
    private const BLOCKED_NETWORKS = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
        '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
        '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '::ffff:0:0/96', '100::/64', '2001:db8::/32',
        'fc00::/7', 'fe80::/10', 'ff00::/8',
    ];

    private readonly DnsResolver $resolver;

    public function __construct(?DnsResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new DnsResolver;
    }

    public function normalize(string $target, bool $allowIp = true): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $target)) {
            throw new InvalidArgumentException(__('ui.invalid_target'));
        }

        $target = strtolower(rtrim(trim($target), '.'));

        if ($allowIp && filter_var($target, FILTER_VALIDATE_IP) !== false) {
            return $target;
        }

        if ($target === '' || preg_match('/[\s\/@?#\\\\:]/', $target)
            || ! str_contains($target, '.')
            || filter_var($target, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || $target === 'localhost'
            || str_ends_with($target, '.localhost')) {
            throw new InvalidArgumentException(__('ui.invalid_target'));
        }

        return $target;
    }

    public function assertSafeDnsTarget(string $target): array
    {
        $target = $this->normalize($target);
        if (filter_var($target, FILTER_VALIDATE_IP) !== false) {
            $this->assertPublicIp($target);

            return [$target];
        }

        return $this->assertResolvedAddresses($target);
    }

    public function assertPublicDomain(string $domain): array
    {
        $domain = $this->normalize($domain, false);

        return $this->assertResolvedAddresses($domain);
    }

    public function assertPublicIp(string $ip): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            || collect(self::BLOCKED_NETWORKS)->contains(fn (string $cidr) => $this->ipInCidr($ip, $cidr))) {
            throw new InvalidArgumentException(__('ui.unsafe_target'));
        }
    }

    public function pinnedHttpsOptions(string $domain, array $validatedIps, ?int $maxBytes = null): array
    {
        $ip = $validatedIps[0] ?? null;
        if (! is_string($ip)) {
            throw new InvalidArgumentException(__('ui.unresolved_target'));
        }
        $this->assertPublicIp($ip);

        if (! defined('CURLOPT_RESOLVE')) {
            throw new \RuntimeException('The cURL extension with CURLOPT_RESOLVE is required for safe outbound HTTP requests.');
        }

        $curlAddress = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        $options = [
            'allow_redirects' => false,
            'curl' => [CURLOPT_RESOLVE => ["{$domain}:443:{$curlAddress}"]],
        ];

        if ($maxBytes !== null && $maxBytes > 0) {
            $options = [...$options, ...$this->responseLimitOptions($maxBytes)];
        }

        return $options;
    }

    public function responseLimitOptions(int $maxBytes): array
    {
        return [
            'on_headers' => static function ($response) use ($maxBytes): void {
                $length = (int) $response->getHeaderLine('Content-Length');
                if ($length > $maxBytes) {
                    throw new ReconLookupException(__('ui.response_too_large'));
                }
            },
            'progress' => static function (int $downloadTotal, int $downloaded) use ($maxBytes): void {
                if ($downloaded > $maxBytes || $downloadTotal > $maxBytes) {
                    throw new ReconLookupException(__('ui.response_too_large'));
                }
            },
        ];
    }

    public function dnsRecords(string $hostname, int $type): array
    {
        return $this->queryDnsRecords($hostname, $type) ?? [];
    }

    public function queryDnsRecords(string $hostname, int $type): ?array
    {
        return $this->resolver->records($hostname, $type);
    }

    public function ipv4Addresses(string $hostname): array
    {
        return $this->resolver->ipv4Addresses($hostname);
    }

    private function assertResolvedAddresses(string $domain): array
    {
        $ips = [];
        $pending = [$domain];
        $visited = [];

        while ($pending !== []) {
            $hostname = array_shift($pending);
            if (isset($visited[$hostname])) {
                continue;
            }
            if (count($visited) >= 10) {
                throw new InvalidArgumentException(__('ui.unresolved_target'));
            }
            $visited[$hostname] = true;

            foreach ([DNS_A, DNS_AAAA, DNS_CNAME] as $type) {
                $records = $this->queryDnsRecords($hostname, $type);
                if ($records === null) {
                    throw new InvalidArgumentException(__('ui.unresolved_target'));
                }
                foreach ($records as $record) {
                    $recordType = strtoupper((string) ($record['type'] ?? ''));
                    if ($recordType === 'A' && isset($record['ip'])) {
                        $ips[] = $record['ip'];
                    } elseif ($recordType === 'AAAA' && isset($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    } elseif ($recordType === 'CNAME' && isset($record['target'])) {
                        $pending[] = $this->normalize((string) $record['target'], false);
                    }
                }
            }
        }
        $ips = array_values(array_unique(array_filter($ips)));

        if ($ips === []) {
            throw new InvalidArgumentException(__('ui.unresolved_target'));
        }

        foreach ($ips as $ip) {
            $this->assertPublicIp($ip);
        }

        return $ips;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);
        $ipBytes = inet_pton($ip);
        $networkBytes = inet_pton($network);
        if ($ipBytes === false || $networkBytes === false || strlen($ipBytes) !== strlen($networkBytes)) {
            return false;
        }

        $prefix = (int) $prefix;
        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBytes[$fullBytes]) & $mask) === (ord($networkBytes[$fullBytes]) & $mask);
    }
}
