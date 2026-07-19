<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Recon\DnsResolver;
use App\Services\Recon\TargetGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_home_redirects_to_dns_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dns');
    }

    public function test_dns_page_is_available(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dns')
            ->assertOk()
            ->assertSee('DNS Recon &amp; Security Investigation', false)
            ->assertSee('lookupForm')
            ->assertSee('Địa chỉ IP')
            ->assertSee('Phạm vi')
            ->assertSee('Nguồn dữ liệu')
            ->assertSee('Đang kiểm tra')
            ->assertSee('data-status="loading"', false)
            ->assertSee('Bản đồ vị trí IP')
            ->assertSee('Các bản ghi được nhóm theo loại')
            ->assertSee('id="ipMap"', false)
            ->assertSee('id="ipsGeo"', false)
            ->assertSee('Nhà đăng ký')
            ->assertSee('Ngày hết hạn')
            ->assertSee('Nmap');
    }

    public function test_lookup_rejects_an_invalid_target(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/dns/lookup', ['target' => 'https://example.com/path'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target');
    }

    public function test_lookup_requires_a_target(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/dns/lookup', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target');
    }

    public function test_guest_cannot_open_dns_page(): void
    {
        $this->get('/dns')->assertRedirect('/login');
    }

    public function test_lookup_rejects_private_ip_targets(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/dns/lookup', ['target' => '192.168.1.25'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target');
    }

    public function test_public_ip_endpoint_returns_classified_ip(): void
    {
        Http::fake([
            'api.ipify.org*' => Http::response(['ip' => '8.8.8.8']),
        ]);
        $this->actingAs(User::factory()->create());

        $this->getJson('/network/public-ip')
            ->assertOk()
            ->assertJsonPath('ip', '8.8.8.8')
            ->assertJsonPath('details.public', true)
            ->assertJsonPath('details.scope', 'public')
            ->assertJsonPath('details.range', 'Global unicast');
    }

    public function test_public_ip_endpoint_falls_back_when_provider_fails(): void
    {
        Http::fake([
            'api.ipify.org*' => Http::response([], 503),
        ]);
        $this->actingAs(User::factory()->create());

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson('/network/public-ip')
            ->assertOk()
            ->assertJsonPath('source', 'request-fallback')
            ->assertJsonPath('details.scope', 'loopback');
    }

    public function test_rdap_response_is_normalized_for_the_interface(): void
    {
        $resolver = Mockery::mock(DnsResolver::class);
        $resolver->shouldReceive('records')->andReturnUsing(fn (string $hostname, int $type): array => $type === DNS_A
            ? [['type' => 'A', 'ip' => '8.8.8.8']]
            : []);
        $this->app->instance(TargetGuard::class, new TargetGuard($resolver));
        Http::fake([
            'https://rdap.org/domain/example.test' => Http::response([
                'objectClassName' => 'domain',
                'handle' => 'TEST-123',
                'ldhName' => 'EXAMPLE.TEST',
                'status' => ['active'],
                'events' => [
                    ['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z'],
                    ['eventAction' => 'expiration', 'eventDate' => '2030-01-01T00:00:00Z'],
                ],
                'nameservers' => [
                    ['ldhName' => 'NS1.EXAMPLE.TEST'],
                    ['ldhName' => 'NS2.EXAMPLE.TEST'],
                ],
                'secureDNS' => ['delegationSigned' => true],
                'entities' => [[
                    'handle' => 'REGISTRAR-1',
                    'roles' => ['registrar'],
                    'vcardArray' => ['vcard', [['fn', [], 'text', 'Example Registrar']]],
                ]],
            ]),
        ]);
        $this->actingAs(User::factory()->create());

        $this->postJson('/dns/lookup', ['target' => 'example.test'])
            ->assertOk()
            ->assertJsonPath('rdap_summary.domain', 'EXAMPLE.TEST')
            ->assertJsonPath('rdap_summary.handle', 'TEST-123')
            ->assertJsonPath('rdap_summary.registrar.0', 'Example Registrar')
            ->assertJsonPath('rdap_summary.nameservers.0', 'NS1.EXAMPLE.TEST')
            ->assertJsonPath('rdap_summary.expires_at', '2030-01-01T00:00:00Z')
            ->assertJsonPath('rdap_summary.dnssec', true);
    }
}
