<?php

namespace Tests\Feature;

use App\Jobs\RunNmapScanJob;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ScannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('scanner.allowlist', ['8.8.8.0/24']);
        Cache::flush();
        RateLimiter::clear('scanner:user:1');
        Queue::fake();
    }

    public function test_authorized_user_can_queue_a_normalized_scan(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/scanner/scans', [
            'target' => '8.8.8.8', 'profile' => 'quick_tcp', 'speed' => 'normal',
            'timeout' => 30, 'authorization_confirmed' => true,
        ])->assertAccepted()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('stage', 'queued');

        $scanId = $response->json('scan_id');
        $this->assertDatabaseHas('scans', ['id' => $scanId, 'user_id' => $user->id, 'target' => '8.8.8.8']);
        Queue::assertPushed(RunNmapScanJob::class, fn ($job) => $job->scanId === $scanId);
    }

    public function test_scan_requires_authorization_confirmation_and_allowlisted_target(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/scanner/scans', [
            'target' => '1.1.1.1', 'profile' => 'quick_tcp',
        ])->assertUnprocessable()->assertJsonValidationErrors('authorization_confirmed');

        $this->actingAs($user)->postJson('/scanner/scans', [
            'target' => '1.1.1.1', 'profile' => 'quick_tcp', 'authorization_confirmed' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('target');
    }

    public function test_identical_double_submit_creates_and_dispatches_only_one_scan(): void
    {
        $user = User::factory()->create();
        $payload = [
            'target' => '8.8.8.8', 'profile' => 'quick_tcp', 'speed' => 'normal',
            'timeout' => 30, 'authorization_confirmed' => true,
        ];

        $this->actingAs($user)->postJson('/scanner/scans', $payload)->assertAccepted();
        $this->postJson('/scanner/scans', $payload)
            ->assertConflict()->assertJsonPath('error', __('scanner.errors.duplicate_submission'));

        $this->assertDatabaseCount('scans', 1);
        Queue::assertPushed(RunNmapScanJob::class, 1);
    }

    public function test_status_results_and_cancel_are_private_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $owner->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'queued',
            'stage' => 'queued', 'authorization_confirmed_at' => now(),
        ]);

        $this->actingAs($other)->getJson("/scanner/scans/{$scan->id}/status")->assertForbidden();
        $this->actingAs($other)->getJson("/scanner/scans/{$scan->id}/results")->assertForbidden();
        $this->actingAs($owner)->postJson("/scanner/scans/{$scan->id}/cancel")
            ->assertOk()->assertJsonPath('status', 'cancelled');
    }

    public function test_only_terminal_scan_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'running',
            'stage' => 'scanning_ports', 'authorization_confirmed_at' => now(),
        ]);
        $this->actingAs($user)->deleteJson("/scanner/scans/{$scan->id}")->assertConflict();
        $scan->update(['status' => 'completed']);
        $this->actingAs($user)->deleteJson("/scanner/scans/{$scan->id}")->assertOk();
        $this->assertDatabaseMissing('scans', ['id' => $scan->id]);
    }

    public function test_rerun_revalidates_target_and_does_not_reuse_remote_scan_metadata(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'completed',
            'stage' => 'completed', 'progress' => 100, 'authorization_confirmed_at' => now(),
            'options' => ['speed' => 'normal', 'timeout' => 30, 'host_count' => 1, 'remote_scan_id' => 99],
        ]);

        $response = $this->actingAs($user)->postJson("/scanner/scans/{$scan->id}/rerun", [
            'authorization_confirmed' => true,
        ])->assertAccepted()->assertJsonPath('status', 'queued');

        $copy = Scan::findOrFail($response->json('scan_id'));
        $this->assertSame($user->id, $copy->user_id);
        $this->assertArrayNotHasKey('remote_scan_id', $copy->options);
        $this->assertSame('127.0.0.1', $copy->options['requested_ip']);
        Queue::assertPushed(RunNmapScanJob::class, fn ($job) => $job->scanId === $copy->id);
    }

    public function test_identical_double_rerun_creates_only_one_new_scan(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'completed',
            'stage' => 'completed', 'progress' => 100, 'authorization_confirmed_at' => now(),
            'options' => ['speed' => 'normal', 'timeout' => 30],
        ]);

        $this->actingAs($user)->postJson("/scanner/scans/{$scan->id}/rerun", ['authorization_confirmed' => true])
            ->assertAccepted();
        $this->postJson("/scanner/scans/{$scan->id}/rerun", ['authorization_confirmed' => true])
            ->assertConflict()->assertJsonPath('error', __('scanner.errors.duplicate_submission'));

        $this->assertDatabaseCount('scans', 2);
        Queue::assertPushed(RunNmapScanJob::class, 1);
    }

    public function test_terminal_scan_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'completed',
            'stage' => 'completed', 'progress' => 100, 'authorization_confirmed_at' => now(),
        ]);

        $this->actingAs($user)->postJson("/scanner/scans/{$scan->id}/cancel")->assertForbidden();
        $this->assertNull($scan->fresh()->cancel_requested_at);
    }

    public function test_dispatch_failure_marks_the_new_scan_failed(): void
    {
        $user = User::factory()->create();
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('queue connection secret detail'));

        $response = $this->actingAs($user)->postJson('/scanner/scans', [
            'target' => '8.8.8.8', 'profile' => 'quick_tcp', 'speed' => 'normal',
            'timeout' => 30, 'authorization_confirmed' => true,
        ])->assertStatus(503)->assertJsonPath('error', __('scanner.errors.queue_unavailable'));

        $this->assertDatabaseHas('scans', [
            'id' => $response->json('scan_id'), 'status' => 'failed',
            'error_message' => __('scanner.errors.queue_unavailable'),
        ]);
    }

    public function test_validation_errors_keep_the_scanner_response_schema(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/scanner/scans', [])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('progress', 0)
            ->assertJsonStructure(['data', 'warnings', 'error', 'errors', 'created_at', 'updated_at']);
    }

    public function test_failed_scan_status_exposes_only_its_safe_user_message(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'failed',
            'stage' => 'failed', 'authorization_confirmed_at' => now(),
            'error_message' => __('scanner.errors.job_failed'),
            'options' => ['requested_ip' => '127.0.0.1', 'remote_scan_id' => 999, 'secret_key' => 'never-return'],
        ]);

        $this->actingAs($user)->getJson("/scanner/scans/{$scan->id}/status")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', __('scanner.errors.job_failed'))
            ->assertJsonMissing(['requested_ip' => '127.0.0.1'])
            ->assertJsonMissing(['remote_scan_id' => 999])
            ->assertJsonMissing(['secret_key' => 'never-return']);
    }
}
