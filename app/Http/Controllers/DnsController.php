<?php

namespace App\Http\Controllers;

use App\Models\ReconHistory;
use App\Services\CreditService;
use App\Services\FeatureAccessService;
use App\Services\Recon\TargetGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DnsController extends Controller
{
    public function __construct(
        private readonly TargetGuard $targetGuard,
        private readonly FeatureAccessService $features,
        private readonly CreditService $credits,
    ) {}

    public function index()
    {
        $user = request()->user();
        $codes = ['security_headers', 'email_security', 'ssl_analysis', 'subdomain_scan', 'technology_fingerprint', 'nmap_scan', 'export_json', 'export_pdf'];
        $featureStatuses = collect($codes)->mapWithKeys(fn ($code) => [$code => $this->features->status($user, $code)]);
        $serviceFeatures = ['security_headers' => 'security_headers', 'email_security' => 'email_security', 'ssl_certificate' => 'ssl_analysis', 'subdomains' => 'subdomain_scan', 'tech_fingerprint' => 'technology_fingerprint', 'nmap' => 'nmap_scan'];

        return view('dns.index', [
            'env' => [
                'allow_url_fopen' => ini_get('allow_url_fopen'),
                'curl_enabled' => extension_loaded('curl'),
                'traceroute_available' => $this->isTracerouteAvailable(),
            ],
            'featureStatuses' => $featureStatuses,
            'allowedReconServices' => collect($serviceFeatures)->filter(fn ($feature) => $featureStatuses[$feature]['allowed'])->keys()->values(),
        ]);
    }

    public function publicIp(Request $request)
    {
        $this->features->authorize($request->user(), 'geo_lookup');
        try {
            $ip = Cache::remember('network:public-ip', now()->addMinutes(5), function () {
                $response = Http::acceptJson()
                    ->withHeaders(['User-Agent' => 'KiemTraDNS/1.0'])
                    ->connectTimeout(3)
                    ->timeout(config('services.dns.http_timeout_seconds', 5))
                    ->withOptions([
                        'allow_redirects' => false,
                        ...$this->targetGuard->responseLimitOptions(4096),
                    ])
                    ->get(config('services.dns.public_ip_url'), ['format' => 'json']);

                return $response->successful() && strlen($response->body()) <= 4096 ? $response->json('ip') : null;
            });

            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new \RuntimeException(__('ui.public_ip_unavailable'));
            }

            return response()->json([
                'ip' => $ip,
                'details' => $this->classifyIp($ip),
                'source' => 'public-egress',
            ]);
        } catch (\Throwable $e) {
            $clientIp = $request->ip();

            return response()->json([
                'ip' => filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : null,
                'details' => filter_var($clientIp, FILTER_VALIDATE_IP) ? $this->classifyIp($clientIp) : null,
                'source' => 'request-fallback',
                'warning' => __('ui.public_ip_unavailable'),
            ]);
        }
    }

    public function lookup(Request $request)
    {
        $request->validate([
            'target' => ['required', 'string', 'max:253'],
            'history_id' => [
                'nullable', 'integer',
                Rule::exists('recon_histories', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
        ]);

        try {
            $normalizedTarget = $this->targetGuard->normalize($request->string('target')->toString());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target' => $exception->getMessage()]);
        }

        $this->throttle($normalizedTarget);
        try {
            $this->targetGuard->assertSafeDnsTarget($normalizedTarget);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target' => $exception->getMessage()]);
        }

        $access = $this->features->authorize($request->user(), 'dns_lookup');
        $reference = $request->integer('history_id') ?: crc32($request->session()->getId().'|'.$normalizedTarget.'|'.hrtime(true));
        $this->credits->charge($request->user(), 'dns_lookup', $access['cost'], ReconHistory::class, $reference);

        $rdapAllowed = $this->features->canUse($request->user(), 'rdap_lookup');
        $geoAllowed = $this->features->canUse($request->user(), 'geo_lookup');
        $cacheKey = 'dns:'.app()->getLocale().':'.(int) $rdapAllowed.':'.(int) $geoAllowed.':'.md5($normalizedTarget);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            $this->storeHistoryResult($request, $normalizedTarget, $cached);

            return response()->json($cached)->header('X-Cache', 'hit');
        }

        $result = [];

        // Query each supported record type separately. DNS_ALL is unreliable on Windows.
        $isDirectIp = filter_var($normalizedTarget, FILTER_VALIDATE_IP) !== false;
        $dnsResult = $isDirectIp
            ? ['records' => [], 'queried_types' => [], 'failed_types' => []]
            : $this->queryDnsRecords($normalizedTarget);
        $records = $dnsResult['records'];
        $result['dns_records'] = $records;
        $result['dns_status'] = [
            'queried_types' => $dnsResult['queried_types'],
            'failed_types' => $dnsResult['failed_types'],
            'message' => empty($records) && ! $isDirectIp ? __('ui.no_dns_records') : null,
        ];

        // Resolve IPs from the successful A/AAAA records, or inspect a directly entered IP.
        $ips = $isDirectIp ? [$normalizedTarget] : [];
        foreach ($records as $record) {
            if (($record['type'] ?? null) === 'A' && ! empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (($record['type'] ?? null) === 'AAAA' && ! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        $ips = array_values(array_unique(array_filter($ips)));
        try {
            foreach ($ips as $ip) {
                $this->targetGuard->assertPublicIp($ip);
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target' => $exception->getMessage()]);
        }
        $result['ips'] = $ips;

        // RDAP/WHOIS via config or rdap.org fallback
        $whois = null;
        if ($rdapAllowed) {
            try {
                if (! filter_var($normalizedTarget, FILTER_VALIDATE_IP)) {
                    $response = Http::withHeaders([
                        'Accept' => 'application/rdap+json, application/json',
                        'User-Agent' => 'KiemTraDNS/1.0',
                    ])
                        ->connectTimeout(3)
                        ->timeout(config('services.dns.http_timeout_seconds', 5))
                        ->withOptions([
                            'allow_redirects' => false,
                            ...$this->targetGuard->responseLimitOptions((int) config('services.dns.response_max_bytes')),
                        ])
                        ->get(rtrim(config('services.dns.rdap_url'), '/').'/'.urlencode($normalizedTarget));

                    if (strlen($response->body()) > (int) config('services.dns.response_max_bytes')) {
                        $result['rdap_error'] = __('ui.response_too_large');
                    } elseif ($response->successful()) {
                        $whois = $response->json();
                    } else {
                        $result['rdap_error'] = __('ui.rdap_http_error', ['status' => $response->status()]);
                    }
                }
                $result['rdap'] = $whois;

                // Parse registrant/operator/contact names from RDAP entities
                $contacts = ['registrant' => [], 'technical' => [], 'administrative' => [], 'registrar' => []];
                if (! empty($whois['entities']) && is_array($whois['entities'])) {
                    foreach ($whois['entities'] as $ent) {
                        $roles = $ent['roles'] ?? [];
                        $name = null;
                        if (! empty($ent['vcardArray'][1]) && is_array($ent['vcardArray'][1])) {
                            foreach ($ent['vcardArray'][1] as $card) {
                                if (in_array($card[0], ['fn', 'org'])) {
                                    $name = $card[3] ?? $card[1] ?? $name;
                                    break;
                                }
                            }
                        }
                        foreach ($roles as $role) {
                            $roleKey = strtolower($role);
                            if (strpos($roleKey, 'registrant') !== false) {
                                $contacts['registrant'][] = $name ?: $ent['handle'] ?? null;
                            } elseif (strpos($roleKey, 'tech') !== false) {
                                $contacts['technical'][] = $name ?: $ent['handle'] ?? null;
                            } elseif (strpos($roleKey, 'admin') !== false) {
                                $contacts['administrative'][] = $name ?: $ent['handle'] ?? null;
                            } elseif (strpos($roleKey, 'registrar') !== false) {
                                $contacts['registrar'][] = $name ?: $ent['handle'] ?? null;
                            }
                        }
                    }
                }
                $result['contacts'] = $contacts;
                $result['rdap_summary'] = $this->summarizeRdap($whois, $contacts);
            } catch (\Throwable $e) {
                $result['rdap'] = null;
                $result['contacts'] = ['registrant' => [], 'technical' => [], 'administrative' => [], 'registrar' => []];
                $result['rdap_summary'] = null;
                $result['rdap_error'] = __('ui.rdap_unavailable');
            }
        } else {
            $result['rdap'] = null;
            $result['rdap_error'] = __('platform.errors.feature_disabled');
        }

        // Geo for each IP using configured provider or ip-api fallback
        $geo = [];
        $enrichedIps = [];
        if ($geoAllowed && ! empty($ips)) {
            try {
                foreach ($ips as $ip) {
                    if (! $this->classifyIp($ip)['public']) {
                        $geo[] = ['notice' => 'Geo lookup skipped for a non-public IP.'];

                        continue;
                    }

                    try {
                        $response = Http::acceptJson()
                            ->connectTimeout(3)
                            ->timeout(config('services.dns.http_timeout_seconds', 5))
                            ->withOptions([
                                'allow_redirects' => false,
                                ...$this->targetGuard->responseLimitOptions((int) config('services.dns.response_max_bytes')),
                            ])
                            ->get(rtrim(config('services.dns.geo_url'), '/').'/'.urlencode($ip));
                        $geo[] = $response->successful() && strlen($response->body()) <= (int) config('services.dns.response_max_bytes')
                            ? $response->json() : ['error' => __('ui.geo_unavailable')];
                    } catch (\Throwable) {
                        $geo[] = ['error' => __('ui.geo_unavailable')];
                    }
                }
                $result['geo'] = $geo;

                foreach ($ips as $i => $ip) {
                    $entry = [...$this->classifyIp($ip), 'geo' => $geo[$i] ?? null];
                    // run traceroute for this IP (best-effort)
                    $entry['traceroute'] = $this->runTraceroute($ip);
                    $enrichedIps[] = $entry;
                }
                $result['enriched_ips'] = $enrichedIps;
            } catch (\Throwable) {
                $result['geo_error'] = __('ui.geo_unavailable');
            }
        }

        $result['meta'] = [
            'target' => $normalizedTarget,
            'cache_ttl_seconds' => config('services.dns.cache_ttl_seconds', 300),
            'traceroute_available' => $this->isTracerouteAvailable(),
            'provider' => config('services.dns.geo_url'),
        ];

        Cache::put($cacheKey, $result, now()->addSeconds(config('services.dns.cache_ttl_seconds', 300)));
        $this->storeHistoryResult($request, $normalizedTarget, $result);

        return response()->json($result)->header('X-Cache', 'miss');
    }

    private function throttle(string $target): void
    {
        $key = 'dns-throttle:'.md5($target);
        $count = Cache::get($key, 0);
        if ($count >= 10) {
            abort(429, 'Too many requests. Please wait a moment before trying again.');
        }
        Cache::put($key, $count + 1, now()->addMinutes(1));
    }

    private function storeHistoryResult(Request $request, string $target, array $result): void
    {
        if (! $request->filled('history_id') || ! $request->user()) {
            return;
        }

        Cache::lock('recon-history:'.$request->integer('history_id'), 10)->block(5, function () use ($request, $target, $result) {
            $history = ReconHistory::whereKey($request->integer('history_id'))
                ->where('user_id', $request->user()->id)
                ->where('target', $target)
                ->first();
            if (! $history) {
                return;
            }

            $results = $history->results ?? [];
            $results['dns'] = [
                'service' => 'dns',
                'target' => $target,
                'status' => empty($result['dns_records']) ? 'warning' : 'safe',
                'score' => null,
                'data' => $result,
                'warnings' => [],
                'error' => null,
                'checked_at' => now()->toIso8601String(),
            ];
            $history->update(['results' => $results]);
        });
    }

    private function queryDnsRecords(string $hostname): array
    {
        $types = [
            'A' => DNS_A,
            'AAAA' => DNS_AAAA,
            'CNAME' => DNS_CNAME,
            'MX' => DNS_MX,
            'NS' => DNS_NS,
            'TXT' => DNS_TXT,
            'SOA' => DNS_SOA,
            'SRV' => DNS_SRV,
        ];

        if (defined('DNS_CAA') && stripos(PHP_OS, 'WIN') !== 0) {
            $types['CAA'] = DNS_CAA;
        }

        $records = [];
        $queriedTypes = [];
        $failedTypes = [];

        foreach ($types as $name => $type) {
            try {
                $items = $this->targetGuard->queryDnsRecords($hostname, $type);
                if ($items === null) {
                    $failedTypes[] = $name;

                    continue;
                }

                $queriedTypes[] = $name;
                foreach ($items as $item) {
                    $records[] = $item;
                }
            } catch (\Throwable) {
                $failedTypes[] = $name;
            }
        }

        $hasARecord = collect($records)->contains(fn (array $record) => ($record['type'] ?? null) === 'A');
        if (! $hasARecord) {
            try {
                $fallbackIps = $this->targetGuard->ipv4Addresses($hostname);
                foreach ($fallbackIps as $ip) {
                    $records[] = [
                        'host' => $hostname,
                        'class' => 'IN',
                        'ttl' => null,
                        'type' => 'A',
                        'ip' => $ip,
                        'source' => 'system-resolver',
                    ];
                }
            } catch (\Throwable) {
                // A failed system resolver is represented by an empty record set.
            }
        }

        return [
            'records' => collect($records)->unique(fn (array $record) => json_encode($record))->values()->all(),
            'queried_types' => array_values(array_unique($queriedTypes)),
            'failed_types' => array_values(array_unique($failedTypes)),
        ];
    }

    private static function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        [$subnet, $bits] = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }

    private function classifyIp(string $ip): array
    {
        $version = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 'IPv6';
        $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        $scope = $public ? 'public' : 'reserved';
        $range = $version === 'IPv4' ? 'Special/reserved range' : 'Special/reserved IPv6 range';

        $knownRanges = $version === 'IPv4' ? [
            '127.0.0.0/8' => 'loopback',
            '10.0.0.0/8' => 'private',
            '172.16.0.0/12' => 'private',
            '192.168.0.0/16' => 'private',
            '169.254.0.0/16' => 'link-local',
            '100.64.0.0/10' => 'shared-carrier-nat',
            '192.0.2.0/24' => 'documentation',
            '198.51.100.0/24' => 'documentation',
            '203.0.113.0/24' => 'documentation',
            '224.0.0.0/4' => 'multicast',
        ] : [];

        foreach ($knownRanges as $cidr => $knownScope) {
            if (self::ipInRange($ip, $cidr)) {
                $scope = $knownScope;
                $range = $cidr;
                break;
            }
        }

        if ($version === 'IPv6') {
            $packed = inet_pton($ip);
            $first = $packed !== false ? ord($packed[0]) : 0;
            if ($ip === '::1') {
                [$scope, $range] = ['loopback', '::1/128'];
            } elseif (($first & 0xFE) === 0xFC) {
                [$scope, $range] = ['private', 'fc00::/7'];
            } elseif ($first === 0xFE && (ord($packed[1]) & 0xC0) === 0x80) {
                [$scope, $range] = ['link-local', 'fe80::/10'];
            } elseif ($first === 0xFF) {
                [$scope, $range] = ['multicast', 'ff00::/8'];
            } elseif (str_starts_with(strtolower($ip), '2001:db8:')) {
                [$scope, $range] = ['documentation', '2001:db8::/32'];
            }
        }

        if ($public) {
            $range = 'Global unicast';
        }

        return compact('ip', 'version', 'public', 'scope', 'range');
    }

    private function summarizeRdap(?array $rdap, array $contacts): ?array
    {
        if (empty($rdap)) {
            return null;
        }

        $events = [];
        foreach ($rdap['events'] ?? [] as $event) {
            if (! empty($event['eventAction']) && ! empty($event['eventDate'])) {
                $events[$event['eventAction']] = $event['eventDate'];
            }
        }

        $nameservers = [];
        foreach ($rdap['nameservers'] ?? [] as $nameserver) {
            $name = $nameserver['unicodeName'] ?? $nameserver['ldhName'] ?? null;
            if ($name) {
                $nameservers[] = $name;
            }
        }

        $selfLink = null;
        foreach ($rdap['links'] ?? [] as $link) {
            if (($link['rel'] ?? null) === 'self' && ! empty($link['href'])) {
                $selfLink = $link['href'];
                break;
            }
        }

        return [
            'domain' => $rdap['unicodeName'] ?? $rdap['ldhName'] ?? null,
            'handle' => $rdap['handle'] ?? null,
            'status' => array_values($rdap['status'] ?? []),
            'registrar' => collect($contacts['registrar'] ?? [])->filter()->unique()->values()->all(),
            'registrant' => collect($contacts['registrant'] ?? [])->filter()->unique()->values()->all(),
            'technical' => collect($contacts['technical'] ?? [])->filter()->unique()->values()->all(),
            'administrative' => collect($contacts['administrative'] ?? [])->filter()->unique()->values()->all(),
            'nameservers' => array_values(array_unique($nameservers)),
            'registered_at' => $events['registration'] ?? null,
            'expires_at' => $events['expiration'] ?? null,
            'updated_at' => $events['last changed'] ?? $events['last update of RDAP database'] ?? null,
            'dnssec' => $rdap['secureDNS']['delegationSigned'] ?? null,
            'self_link' => $selfLink,
        ];
    }

    private function runTraceroute(string $ip): array
    {
        if (! config('services.dns.traceroute_enabled', false)) {
            return ['error' => __('ui.traceroute_disabled')];
        }

        if (! $this->isTracerouteAvailable()) {
            return ['error' => __('ui.traceroute_unavailable')];
        }

        $this->targetGuard->assertPublicIp($ip);
        $binary = (string) config('services.dns.traceroute_binary');
        $command = PHP_OS_FAMILY === 'Windows'
            ? [$binary, '-d', '-h', '30', $ip]
            : [$binary, '-n', '-w', '1', '-q', '1', $ip];

        try {
            $process = Process::timeout((int) config('services.dns.traceroute_timeout_seconds', 35))->run($command);
            if ($process->failed()) {
                return ['error' => __('ui.traceroute_failed')];
            }

            $output = $process->output();
            if (strlen($output) > (int) config('services.dns.traceroute_max_output_bytes', 65536)) {
                return ['error' => __('ui.response_too_large')];
            }

            return array_values(array_filter(array_map('trim', preg_split('/\R/', $output) ?: [])));
        } catch (\Throwable) {
            return ['error' => __('ui.traceroute_failed')];
        }
    }

    private function isTracerouteAvailable(): bool
    {
        if (! config('services.dns.traceroute_enabled', false)) {
            return false;
        }

        $binary = (string) config('services.dns.traceroute_binary');
        if ($binary === '') {
            return false;
        }
        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return is_file($binary);
        }

        try {
            $argument = PHP_OS_FAMILY === 'Windows' ? '/?' : '--version';

            return Process::timeout(2)->run([$binary, $argument])->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
