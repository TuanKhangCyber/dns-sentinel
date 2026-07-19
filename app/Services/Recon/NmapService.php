<?php

namespace App\Services\Recon;

use App\Exceptions\Recon\ReconLookupException;
use App\Services\Recon\Concerns\BuildsReconResults;
use App\Services\Scanner\TargetValidationService;
use Illuminate\Support\Facades\Process;
use SimpleXMLElement;

class NmapService
{
    use BuildsReconResults;

    public function __construct(private readonly TargetValidationService $targets) {}

    public function lookup(string $domain): array
    {
        return $this->cached('nmap', $domain, config('recon.nmap.cache_minutes'), function () use ($domain) {
            if (! config('recon.nmap.enabled')) {
                throw new ReconLookupException(__('ui.nmap_disabled'));
            }

            // The legacy Recon tab is still a scanner entry point. Apply the
            // same fail-closed allowlist and DNS validation as queued scans.
            $validated = $this->targets->validate($domain);
            $ips = $validated['resolved_ips'];
            $targetIp = $ips[0];
            $timeout = max(10, min(60, (int) config('recon.nmap.timeout_seconds')));
            $topPorts = max(10, min(100, (int) config('recon.nmap.top_ports')));
            $command = [
                (string) config('recon.nmap.binary'),
                '-sT', '-Pn', '-n', '-T3',
                '--top-ports', (string) $topPorts,
                '--max-retries', '1',
                '--max-rate', '100',
                '--host-timeout', $timeout.'s',
                '-oX', '-',
                $targetIp,
            ];

            try {
                $process = Process::timeout($timeout + 5)->run($command);
            } catch (\Throwable $exception) {
                throw new ReconLookupException(__('ui.nmap_unavailable'), previous: $exception);
            }

            if ($process->failed()) {
                throw new ReconLookupException(__('ui.nmap_scan_failed'));
            }

            $output = $process->output();
            if (strlen($output) > (int) config('recon.nmap.max_output_bytes')) {
                throw new ReconLookupException(__('ui.nmap_output_too_large'));
            }

            $data = $this->parseXml($output);
            $data['target_ip'] = $targetIp;
            $data['resolved_ips'] = $ips;
            $data['profile'] = "TCP top {$topPorts} ports";
            $openPorts = count($data['ports']);
            $status = $openPorts > 0 ? 'warning' : 'safe';
            $warnings = $openPorts > 0 ? ['open_ports_detected'] : [];

            return $this->result('nmap', $domain, $status, null, $data, $warnings);
        });
    }

    private function parseXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $document instanceof SimpleXMLElement) {
            throw new ReconLookupException(__('ui.nmap_invalid_output'));
        }

        $host = $document->host[0] ?? null;
        $ports = [];
        if ($host instanceof SimpleXMLElement) {
            foreach ($host->ports->port ?? [] as $port) {
                if ((string) $port->state['state'] !== 'open') {
                    continue;
                }
                $ports[] = [
                    'port' => (int) $port['portid'],
                    'protocol' => (string) $port['protocol'],
                    'state' => (string) $port->state['state'],
                    'service' => (string) ($port->service['name'] ?? ''),
                ];
            }
        }

        return [
            'host_up' => $host instanceof SimpleXMLElement && (string) $host->status['state'] === 'up',
            'open_port_count' => count($ports),
            'ports' => $ports,
        ];
    }
}
