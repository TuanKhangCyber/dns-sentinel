<?php

namespace App\Services\Scanner;

use App\Models\Scan;

class ScanReportService
{
    public function payload(Scan $scan): array
    {
        $scan->loadMissing(['user:id,name,email', 'hosts.ports', 'findings.host:id,ip_address,hostname', 'findings.port:id,port,protocol']);
        $safeOptions = collect($scan->options ?? [])->except(['requested_ip', 'remote_scan_id'])->all();

        return [
            'schema_version' => '1.0',
            'company' => config('scanner.company_name'),
            'generated_at' => now()->toIso8601String(),
            'disclaimer' => __('scanner.report_disclaimer'),
            'scan' => [
                'id' => $scan->id, 'target' => $scan->target, 'target_type' => $scan->target_type,
                'profile' => $scan->profile, 'scanner_type' => $scan->scanner_type,
                'status' => $scan->status, 'started_at' => $scan->started_at?->toIso8601String(),
                'completed_at' => $scan->completed_at?->toIso8601String(), 'duration_seconds' => $scan->duration_seconds,
                'performed_by' => ['name' => $scan->user?->name, 'email' => $scan->user?->email],
                'configuration' => $safeOptions, 'summary' => $scan->summary ?? [],
            ],
            'hosts' => $scan->hosts->map(fn ($host) => [
                'ip_address' => $host->ip_address, 'hostname' => $host->hostname, 'status' => $host->status,
                'vendor' => $host->vendor, 'operating_system' => $host->operating_system,
                'os_accuracy' => $host->os_accuracy, 'response_time' => $host->response_time,
                'ports' => $host->ports->map(fn ($port) => [
                    'port' => $port->port, 'protocol' => $port->protocol, 'state' => $port->state,
                    'service' => $port->service, 'product' => $port->product, 'version' => $port->version,
                    'extra_info' => $port->extra_info, 'cpe' => $port->cpe,
                ])->values()->all(),
            ])->values()->all(),
            'findings' => $scan->findings->map(fn ($finding) => [
                'plugin_id' => $finding->plugin_id, 'title' => $finding->title, 'severity' => $finding->severity,
                'status' => $finding->status, 'host' => $finding->host?->ip_address,
                'port' => $finding->port ? $finding->port->protocol.'/'.$finding->port->port : null,
                'cvss_score' => $finding->cvss_score, 'cvss_vector' => $finding->cvss_vector,
                'cve' => $finding->cve ?? [], 'description' => $finding->description,
                'evidence' => $finding->evidence, 'solution' => $finding->solution,
                'references' => $finding->references ?? [],
            ])->values()->all(),
        ];
    }

    public function csv(array $report): string
    {
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, ['section', 'host', 'port', 'protocol', 'state_or_severity', 'name', 'details']);

        foreach ($report['hosts'] as $host) {
            fputcsv($stream, $this->safeCsv(['host', $host['ip_address'], null, null, $host['status'], $host['hostname'], $host['operating_system']]));
            foreach ($host['ports'] as $port) {
                fputcsv($stream, $this->safeCsv(['port', $host['ip_address'], $port['port'], $port['protocol'], $port['state'], $port['service'], trim(implode(' ', array_filter([$port['product'], $port['version'], $port['extra_info']])))]));
            }
        }
        foreach ($report['findings'] as $finding) {
            fputcsv($stream, $this->safeCsv(['finding', $finding['host'], $finding['port'], null, $finding['severity'], $finding['title'], $finding['solution'] ?? $finding['description']]));
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$csv;
    }

    private function safeCsv(array $row): array
    {
        return array_map(function (mixed $value): mixed {
            if (is_string($value) && preg_match('/^[=+\-@]/', ltrim($value))) {
                return "'".$value;
            }

            return $value;
        }, $row);
    }
}
