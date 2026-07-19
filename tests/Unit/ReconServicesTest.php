<?php

namespace Tests\Unit;

use App\Services\Recon\EmailSecurityService;
use App\Services\Recon\SecurityHeadersService;
use App\Services\Recon\SslCertificateService;
use App\Services\Recon\SubdomainScannerService;
use App\Services\Recon\TargetGuard;
use App\Services\Recon\TechFingerprintService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class ReconServicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_security_headers_are_scored_and_normalized(): void
    {
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('assertPublicDomain')->once()->andReturn(['93.184.216.34']);
        $guard->shouldReceive('pinnedHttpsOptions')->once()->andReturn(['allow_redirects' => false]);
        Http::fake(['https://example.com' => Http::response('', 200, [
            'Strict-Transport-Security' => 'max-age=31536000',
            'Content-Security-Policy' => "default-src 'self'",
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin',
            'Permissions-Policy' => 'camera=()',
        ])]);

        $result = (new SecurityHeadersService($guard))->lookup('example.com');

        $this->assertSame('safe', $result['status']);
        $this->assertSame(100, $result['score']);
        $this->assertCount(6, $result['data']['headers']);
    }

    public function test_missing_security_headers_are_high_risk(): void
    {
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('assertPublicDomain')->once()->andReturn(['93.184.216.34']);
        $guard->shouldReceive('pinnedHttpsOptions')->once()->andReturn(['allow_redirects' => false]);
        Http::fake(['https://unsafe.example' => Http::response('', 200)]);

        $result = (new SecurityHeadersService($guard))->lookup('unsafe.example');

        $this->assertSame('danger', $result['status']);
        $this->assertSame(0, $result['score']);
    }

    public function test_security_headers_do_not_follow_redirect_to_private_target(): void
    {
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('assertPublicDomain')->once()->andReturn(['8.8.8.8']);
        $guard->shouldReceive('pinnedHttpsOptions')->once()->andReturn(['allow_redirects' => false]);
        Http::fake([
            'https://redirect.example' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
            'http://127.0.0.1/*' => Http::response('private', 200),
        ]);

        $result = (new SecurityHeadersService($guard))->lookup('redirect.example');

        $this->assertSame('error', $result['status']);
        $this->assertSame(__('ui.lookup_failed', ['status' => 302]), $result['error']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'http://127.0.0.1'));
    }

    public function test_email_security_returns_clear_missing_record_warnings(): void
    {
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('assertSafeDnsTarget')->once()->andReturn([]);
        $guard->shouldReceive('dnsRecords')->times(5)->andReturn([]);
        $result = (new EmailSecurityService($guard))->lookup('definitely-invalid.example');

        $this->assertSame('danger', $result['status']);
        $this->assertContains('spf_missing', $result['warnings']);
        $this->assertContains('dmarc_missing', $result['warnings']);
    }

    public function test_subdomains_are_deduplicated_from_certificate_transparency(): void
    {
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('assertSafeDnsTarget')->once()->andReturn([]);
        $guard->shouldReceive('responseLimitOptions')->once()->andReturn([]);
        $guard->shouldReceive('dnsRecords')->times(4)->andReturn([]);
        Http::fake(['https://crt.sh/*' => Http::response([
            ['name_value' => "www.invalid.example\napi.invalid.example"],
            ['name_value' => '*.invalid.example'],
            ['name_value' => 'www.invalid.example'],
        ])]);

        $result = (new SubdomainScannerService($guard))->lookup('invalid.example');

        $this->assertSame(2, $result['data']['discovered_count']);
        $this->assertSame(['api.invalid.example', 'www.invalid.example'], array_column($result['data']['subdomains'], 'name'));
    }

    public function test_technology_fingerprint_detects_framework_and_waf(): void
    {
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('assertPublicDomain')->once()->andReturn(['93.184.216.34']);
        $guard->shouldReceive('pinnedHttpsOptions')->once()->andReturn(['allow_redirects' => false]);
        Http::fake(['https://example.com' => Http::response(
            '<meta name="generator" content="WordPress 6"><script src="/wp-content/app.js"></script>',
            200,
            ['Server' => 'cloudflare', 'CF-Ray' => 'abc'],
        )]);

        $result = (new TechFingerprintService($guard))->lookup('example.com');

        $this->assertContains('WordPress', $result['data']['technologies']);
        $this->assertContains('Cloudflare', $result['data']['cdn_waf']);
    }

    public function test_ssl_service_normalizes_connection_guard_errors(): void
    {
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('assertPublicDomain')->twice()->andThrow(new InvalidArgumentException('Private target blocked.'));

        $service = new SslCertificateService($guard);
        $result = $service->lookup('internal.example');
        $retry = $service->lookup('internal.example');

        $this->assertSame('error', $result['status']);
        $this->assertSame('Private target blocked.', $result['error']);
        $this->assertSame('error', $retry['status']);
    }
}
