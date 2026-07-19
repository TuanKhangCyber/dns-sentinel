<?php

namespace Tests\Unit;

use App\Services\Recon\NmapService;
use App\Services\Scanner\TargetValidationService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

class NmapServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('recon.nmap.enabled', true);
        config()->set('recon.nmap.binary', 'nmap');
    }

    public function test_it_runs_a_limited_scan_and_normalizes_open_ports(): void
    {
        $targets = Mockery::mock(TargetValidationService::class);
        $targets->shouldReceive('validate')->once()->with('example.com')->andReturn([
            'target' => 'example.com',
            'target_type' => 'hostname',
            'resolved_ips' => ['8.8.8.8'],
            'host_count' => 1,
        ]);
        Process::fake(['*' => Process::result(output: <<<'XML'
<?xml version="1.0"?>
<nmaprun><host><status state="up"/><ports>
<port protocol="tcp" portid="80"><state state="open"/><service name="http"/></port>
<port protocol="tcp" portid="443"><state state="open"/><service name="https"/></port>
<port protocol="tcp" portid="22"><state state="closed"/></port>
</ports></host></nmaprun>
XML)]);

        $result = (new NmapService($targets))->lookup('example.com');

        $this->assertSame('warning', $result['status']);
        $this->assertSame(2, $result['data']['open_port_count']);
        $this->assertSame(80, $result['data']['ports'][0]['port']);
        Process::assertRan(fn (PendingProcess $process) => is_array($process->command)
            && $process->command[0] === 'nmap'
            && in_array('--top-ports', $process->command, true)
            && end($process->command) === '8.8.8.8'
            && ! in_array('example.com', $process->command, true));
    }

    public function test_disabled_nmap_returns_a_normalized_error_without_starting_a_process(): void
    {
        config()->set('recon.nmap.enabled', false);
        Process::fake();
        $targets = Mockery::mock(TargetValidationService::class);

        $result = (new NmapService($targets))->lookup('example.com');

        $this->assertSame('error', $result['status']);
        $this->assertSame(__('ui.nmap_disabled'), $result['error']);
        Process::assertNothingRan();
    }

    public function test_allowlist_rejection_does_not_start_nmap(): void
    {
        Process::fake();
        $targets = Mockery::mock(TargetValidationService::class);
        $targets->shouldReceive('validate')->once()->andThrow(new \InvalidArgumentException('Target is not allowed.'));

        $result = (new NmapService($targets))->lookup('example.com');

        $this->assertSame('error', $result['status']);
        $this->assertSame('Target is not allowed.', $result['error']);
        Process::assertNothingRan();
    }
}
