<?php

namespace App\Services\Recon;

use App\Exceptions\Recon\ReconLookupException;
use App\Services\Recon\Concerns\BuildsReconResults;
use Illuminate\Support\Facades\Http;

class TechFingerprintService
{
    use BuildsReconResults;

    public function __construct(private readonly TargetGuard $guard) {}

    public function lookup(string $domain): array
    {
        return $this->cached('tech_fingerprint', $domain, config('recon.cache_minutes'), function () use ($domain) {
            $ips = $this->guard->assertPublicDomain($domain);
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 DNS-Recon-Security/1.0',
                'Range' => 'bytes=0-524287',
            ])->connectTimeout(4)
                ->timeout(config('recon.timeout_seconds'))
                ->withOptions($this->guard->pinnedHttpsOptions($domain, $ips, (int) config('recon.http_response_max_bytes')))
                ->get('https://'.$domain);

            if (! $response->successful()) {
                throw new ReconLookupException(__('ui.lookup_failed', ['status' => $response->status()]));
            }

            $html = substr($response->body(), 0, 524288);
            $headers = array_change_key_case($response->headers(), CASE_LOWER);
            $technologies = [];
            $generator = null;
            if (preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)/i', $html, $match)
                || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']generator["\']/i', $html, $match)) {
                $generator = trim($match[1]);
                $technologies[] = $generator;
            }

            $patterns = [
                'WordPress' => '/wp-content|wp-includes/i',
                'Drupal' => '/sites\/default\/files|Drupal\.settings/i',
                'Joomla' => '/\/media\/system\/js\/|option=com_/i',
                'React' => '/data-reactroot|__NEXT_DATA__|react(?:\.production)?\.min\.js/i',
                'Vue.js' => '/data-v-[a-f0-9]+|vue(?:\.runtime)?(?:\.min)?\.js/i',
                'Angular' => '/ng-version|angular(?:\.min)?\.js/i',
                'Bootstrap' => '/bootstrap(?:\.min)?\.(?:css|js)/i',
                'Tailwind CSS' => '/tailwind(?:\.min)?\.css/i',
                'Laravel' => '/laravel_session|csrf-token/i',
            ];
            foreach ($patterns as $technology => $pattern) {
                if (preg_match($pattern, $html)) {
                    $technologies[] = $technology;
                }
            }

            $waf = [];
            $server = $headers['server'][0] ?? null;
            $headerText = strtolower(json_encode($headers));
            $wafPatterns = [
                'Cloudflare' => ['cloudflare', 'cf-ray'],
                'Akamai' => ['akamai', 'x-akamai'],
                'Sucuri' => ['sucuri', 'x-sucuri'],
                'Fastly' => ['fastly', 'x-served-by'],
                'AWS CloudFront' => ['cloudfront', 'x-amz-cf-id'],
            ];
            foreach ($wafPatterns as $provider => $needles) {
                if (collect($needles)->contains(fn ($needle) => str_contains($headerText, $needle))) {
                    $waf[] = $provider;
                }
            }

            return $this->result('tech_fingerprint', $domain, 'safe', null, [
                'http_status' => $response->status(),
                'server' => $server,
                'powered_by' => $headers['x-powered-by'][0] ?? null,
                'generator' => $generator,
                'technologies' => array_values(array_unique($technologies)),
                'cdn_waf' => array_values(array_unique($waf)),
            ]);
        });
    }
}
