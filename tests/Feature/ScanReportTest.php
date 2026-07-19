<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_json_csv_and_pdf_without_internal_options(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id, 'target' => 'example.com', 'target_type' => 'hostname',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'completed',
            'stage' => 'completed', 'progress' => 100, 'authorization_confirmed_at' => now(),
            'options' => ['speed' => 'normal', 'requested_ip' => '127.0.0.1', 'remote_scan_id' => 88],
            'summary' => ['hosts' => 1, 'open_ports' => 1, 'findings' => 1],
        ]);
        $host = $scan->hosts()->create(['ip_address' => '93.184.216.34', 'hostname' => 'example.com', 'status' => 'up']);
        $port = $host->ports()->create(['port' => 443, 'protocol' => 'tcp', 'state' => 'open', 'service' => 'https']);
        $scan->findings()->create([
            'scan_host_id' => $host->id, 'scan_port_id' => $port->id, 'plugin_id' => '42',
            'title' => '=HYPERLINK("https://invalid.test")', 'severity' => 'medium', 'status' => 'potential',
            'cve' => [], 'evidence' => '<script>alert(1)</script>', 'solution' => 'Review configuration.',
        ]);

        $json = $this->actingAs($user)->get(route('scanner.scans.export.json', $scan));
        $json->assertOk()->assertHeader('content-type', 'application/json; charset=UTF-8');
        $this->assertStringNotContainsString('requested_ip', $json->streamedContent());
        $this->assertStringNotContainsString('remote_scan_id', $json->streamedContent());

        $csv = $this->actingAs($user)->get(route('scanner.scans.export.csv', $scan));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=HYPERLINK", $csv->streamedContent());

        $pdf = $this->actingAs($user)->get(route('scanner.scans.export.pdf', $scan));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_another_user_cannot_export_a_scan(): void
    {
        $scan = Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => 'example.com', 'target_type' => 'hostname',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'completed',
            'stage' => 'completed', 'authorization_confirmed_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())->get(route('scanner.scans.export.json', $scan))->assertForbidden();
    }
}
