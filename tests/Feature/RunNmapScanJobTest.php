<?php

namespace Tests\Feature;

use App\Jobs\RunNmapScanJob;
use App\Models\Scan;
use App\Models\User;
use App\Services\Scanner\NmapScannerService;
use App\Services\Scanner\ScanRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RunNmapScanJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_persists_normalized_hosts_and_ports(): void
    {
        $scan = Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'status' => 'queued', 'stage' => 'queued', 'scanner_type' => 'nmap',
            'authorization_confirmed_at' => now(), 'options' => ['speed' => 'normal', 'timeout' => 30],
        ]);
        $scanner = Mockery::mock(NmapScannerService::class);
        $scanner->shouldReceive('run')->once()->andReturn([
            'duration_seconds' => 2,
            'hosts' => [[
                'ip_address' => '8.8.8.8', 'hostname' => 'dns.google', 'status' => 'up',
                'raw_data' => [], 'ports' => [[
                    'port' => 443, 'protocol' => 'tcp', 'state' => 'open', 'service' => 'https',
                    'product' => null, 'version' => null, 'extra_info' => null, 'cpe' => null, 'banner' => null,
                ]],
            ]],
        ]);

        $job = new RunNmapScanJob($scan->id);
        $job->handle($scanner, new ScanRiskService);
        $job->handle($scanner, new ScanRiskService);

        $scan->refresh();
        $this->assertSame('completed', $scan->status);
        $this->assertSame(100, $scan->progress);
        $this->assertSame(1, $scan->summary['open_ports']);
        $this->assertDatabaseHas('scan_ports', ['port' => 443, 'state' => 'open']);
        $this->assertDatabaseCount('scan_hosts', 1);
        $this->assertDatabaseCount('scan_ports', 1);
    }

    public function test_cancelled_scan_never_starts_the_process(): void
    {
        $scan = Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'status' => 'queued', 'stage' => 'queued', 'scanner_type' => 'nmap',
            'authorization_confirmed_at' => now(), 'cancel_requested_at' => now(),
        ]);
        $scanner = Mockery::mock(NmapScannerService::class);
        $scanner->shouldNotReceive('run');

        (new RunNmapScanJob($scan->id))->handle($scanner, new ScanRiskService);

        $this->assertSame('cancelled', $scan->fresh()->status);
    }

    public function test_unexpected_failure_is_sanitized_and_marks_scan_failed(): void
    {
        $scan = Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'status' => 'queued', 'stage' => 'queued', 'scanner_type' => 'nmap',
            'authorization_confirmed_at' => now(),
        ]);
        $scanner = Mockery::mock(NmapScannerService::class);
        $scanner->shouldReceive('run')->once()->andThrow(new RuntimeException('C:\\secret\\internal.txt'));

        try {
            (new RunNmapScanJob($scan->id))->handle($scanner, new ScanRiskService);
            $this->fail('The job should rethrow the worker exception.');
        } catch (RuntimeException) {
            // Laravel records the final queue failure after the exception is rethrown.
        }

        $scan->refresh();
        $this->assertSame('failed', $scan->status);
        $this->assertSame(__('scanner.errors.job_failed'), $scan->error_message);
        $this->assertStringNotContainsString('secret', $scan->error_message);
    }

    public function test_cancel_requested_while_scanner_finishes_prevents_result_persistence(): void
    {
        $scan = Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'status' => 'queued', 'stage' => 'queued', 'scanner_type' => 'nmap',
            'authorization_confirmed_at' => now(),
        ]);
        $scanner = Mockery::mock(NmapScannerService::class);
        $scanner->shouldReceive('run')->once()->andReturnUsing(function () use ($scan): array {
            $scan->update(['cancel_requested_at' => now()]);

            return ['duration_seconds' => 1, 'hosts' => [[
                'ip_address' => '8.8.8.8', 'status' => 'up', 'raw_data' => [], 'ports' => [],
            ]]];
        });

        (new RunNmapScanJob($scan->id))->handle($scanner, new ScanRiskService);

        $this->assertSame('cancelled', $scan->fresh()->status);
        $this->assertDatabaseCount('scan_hosts', 0);
    }

    public function test_second_worker_cannot_claim_scan_already_being_processed(): void
    {
        $scan = Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'status' => 'validating', 'stage' => 'validating', 'scanner_type' => 'nmap',
            'authorization_confirmed_at' => now(), 'started_at' => now(),
        ]);
        $scanner = Mockery::mock(NmapScannerService::class);
        $scanner->shouldNotReceive('run');

        (new RunNmapScanJob($scan->id))->handle($scanner, new ScanRiskService);

        $this->assertSame('validating', $scan->fresh()->status);
    }

    public function test_failed_hook_closes_a_scan_left_running_by_worker_timeout(): void
    {
        $scan = Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'status' => 'scanning_ports', 'stage' => 'scanning_ports', 'scanner_type' => 'nmap',
            'authorization_confirmed_at' => now(), 'started_at' => now()->subSecond(),
        ]);

        (new RunNmapScanJob($scan->id))->failed(new RuntimeException('worker timeout'));

        $this->assertSame('failed', $scan->fresh()->status);
        $this->assertNotNull($scan->fresh()->completed_at);
    }

    public function test_failed_hook_does_not_overwrite_completed_scan(): void
    {
        $scan = Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'status' => 'completed', 'stage' => 'completed', 'scanner_type' => 'nmap',
            'progress' => 100, 'authorization_confirmed_at' => now(), 'completed_at' => now(),
        ]);

        (new RunNmapScanJob($scan->id))->failed(new RuntimeException('late worker failure'));

        $this->assertSame('completed', $scan->fresh()->status);
        $this->assertNull($scan->fresh()->error_message);
    }
}
