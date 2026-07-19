<?php

namespace Tests\Feature;

use App\Models\ReconHistory;
use App\Models\User;
use App\Services\Recon\NmapService;
use App\Services\Recon\SecurityHeadersService;
use App\Services\Recon\TargetGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReconWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_recon_rejects_localhost_before_creating_history(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/recon/start', ['target' => 'localhost'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target');

        $this->assertDatabaseCount('recon_histories', 0);
    }

    public function test_user_can_start_scan_and_result_is_saved_to_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('normalize')->andReturn('example.com');
        $guard->shouldReceive('assertSafeDnsTarget')->andReturn(['93.184.216.34']);
        $this->app->instance(TargetGuard::class, $guard);
        $start = $this->postJson('/recon/start', ['target' => 'example.com'])
            ->assertCreated()
            ->assertJsonPath('target', 'example.com');
        $historyId = $start->json('history_id');

        $service = Mockery::mock(SecurityHeadersService::class);
        $service->shouldReceive('lookup')->once()->with('example.com')->andReturn([
            'service' => 'security_headers', 'target' => 'example.com', 'status' => 'safe',
            'score' => 100, 'data' => ['headers' => []], 'warnings' => [], 'error' => null,
            'checked_at' => now()->toIso8601String(),
        ]);
        $this->app->instance(SecurityHeadersService::class, $service);

        $this->postJson('/recon/scan/security_headers', [
            'target' => 'example.com', 'history_id' => $historyId,
        ])->assertOk()->assertJsonPath('result.score', 100);

        $history = ReconHistory::findOrFail($historyId);
        $this->assertSame(100, $history->overall_score);
        $this->assertSame('safe', $history->results['security_headers']['status']);
    }

    public function test_history_is_private_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $history = ReconHistory::create(['user_id' => $owner->id, 'target' => 'example.com', 'results' => []]);

        $this->actingAs($other)->getJson('/recon/history/'.$history->id)->assertNotFound();
        $this->actingAs($other)->get('/recon/history/'.$history->id.'/export/json')->assertNotFound();
    }

    public function test_foreign_history_cannot_be_attached_to_a_recon_service_result(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $history = ReconHistory::create(['user_id' => $owner->id, 'target' => 'example.com', 'results' => []]);

        $this->actingAs($other)->postJson('/recon/scan/security_headers', [
            'target' => 'example.com', 'history_id' => $history->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('history_id');

        $this->assertSame([], $history->fresh()->results);
    }

    public function test_nmap_result_can_be_saved_through_the_existing_scan_endpoint(): void
    {
        config()->set('scanner.allowlist', ['example.com', '8.8.8.8']);
        $user = User::factory()->create();
        $history = ReconHistory::create(['user_id' => $user->id, 'target' => 'example.com', 'results' => []]);
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('normalize')->andReturn('example.com');
        $guard->shouldReceive('assertSafeDnsTarget')->andReturn(['8.8.8.8']);
        $this->app->instance(TargetGuard::class, $guard);
        $nmap = Mockery::mock(NmapService::class);
        $nmap->shouldReceive('lookup')->once()->with('example.com')->andReturn([
            'service' => 'nmap', 'target' => 'example.com', 'status' => 'warning',
            'score' => null, 'data' => ['open_port_count' => 1, 'ports' => [['port' => 443]]],
            'warnings' => ['open_ports_detected'], 'error' => null, 'checked_at' => now()->toIso8601String(),
        ]);
        $this->app->instance(NmapService::class, $nmap);

        $this->actingAs($user)->postJson('/recon/scan/nmap', [
            'target' => 'example.com', 'history_id' => $history->id,
        ])->assertOk()->assertJsonPath('result.data.open_port_count', 1);

        $this->assertSame(443, ReconHistory::findOrFail($history->id)->results['nmap']['data']['ports'][0]['port']);
    }

    public function test_history_can_be_exported_as_json_and_pdf(): void
    {
        $user = User::factory()->create();
        $history = ReconHistory::create([
            'user_id' => $user->id,
            'target' => 'example.com',
            'status' => 'safe',
            'overall_score' => 95,
            'results' => ['security_headers' => [
                'status' => 'safe', 'score' => 95, 'checked_at' => now()->toIso8601String(),
                'warnings' => [], 'data' => ['http_status' => 200],
            ]],
        ]);
        $this->actingAs($user);

        $this->get('/recon/history/'.$history->id.'/export/json')
            ->assertOk()
            ->assertHeader('content-type', 'application/json; charset=UTF-8');

        $pdf = $this->get('/recon/history/'.$history->id.'/export/pdf')->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }
}
