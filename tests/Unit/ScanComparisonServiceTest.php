<?php

namespace Tests\Unit;

use App\Models\Scan;
use App\Models\User;
use App\Services\Scanner\ScanComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_compares_all_hosts_ports_and_findings(): void
    {
        $user = User::factory()->create();
        $left = $this->scan($user, 'left.example');
        $right = $this->scan($user, 'right.example');
        $leftHost = $left->hosts()->create(['ip_address' => '203.0.113.10', 'status' => 'up']);
        $leftHost->ports()->create(['port' => 80, 'protocol' => 'tcp', 'state' => 'open']);
        $rightHost = $right->hosts()->create(['ip_address' => '203.0.113.20', 'status' => 'up']);
        $rightHost->ports()->create(['port' => 443, 'protocol' => 'tcp', 'state' => 'open']);
        $left->findings()->create(['scan_host_id' => $leftHost->id, 'plugin_id' => '1', 'title' => 'Old', 'severity' => 'low']);
        $right->findings()->create(['scan_host_id' => $rightHost->id, 'plugin_id' => '2', 'title' => 'New', 'severity' => 'high']);

        $result = app(ScanComparisonService::class)->compare($left, $right);

        $this->assertSame(['203.0.113.20'], $result['hosts']['added']);
        $this->assertSame(['203.0.113.10:tcp/80'], $result['open_ports']['removed']);
        $this->assertStringContainsString('2', $result['findings']['added'][0]);
    }

    private function scan(User $user, string $target): Scan
    {
        return Scan::create([
            'user_id' => $user->id, 'target' => $target, 'target_type' => 'hostname',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'completed',
            'stage' => 'completed', 'authorization_confirmed_at' => now(),
        ]);
    }
}
