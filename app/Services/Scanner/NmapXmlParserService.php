<?php

namespace App\Services\Scanner;

use App\Exceptions\Scanner\ScannerExecutionException;
use SimpleXMLElement;

class NmapXmlParserService
{
    public function parseFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ScannerExecutionException(__('scanner.errors.output_missing'));
        }

        return $this->parse((string) file_get_contents($path));
    }

    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $document instanceof SimpleXMLElement || $document->getName() !== 'nmaprun') {
            throw new ScannerExecutionException(__('scanner.errors.invalid_xml'));
        }

        $hosts = [];
        foreach ($document->host as $host) {
            $addresses = ['ipv4' => null, 'ipv6' => null, 'mac' => null, 'vendor' => null];
            foreach ($host->address as $address) {
                $type = (string) $address['addrtype'];
                if (array_key_exists($type, $addresses)) {
                    $addresses[$type] = (string) $address['addr'];
                }
                if ($type === 'mac') {
                    $addresses['vendor'] = (string) ($address['vendor'] ?? '');
                }
            }

            $ports = [];
            foreach ($host->ports->port ?? [] as $port) {
                $cpe = isset($port->service->cpe[0]) ? (string) $port->service->cpe[0] : null;
                $ports[] = [
                    'port' => (int) $port['portid'], 'protocol' => (string) $port['protocol'],
                    'state' => mb_substr((string) $port->state['state'], 0, 20),
                    'service' => $this->nullable($port->service['name'] ?? null),
                    'product' => $this->nullable($port->service['product'] ?? null),
                    'version' => $this->nullable($port->service['version'] ?? null),
                    'extra_info' => $this->nullable($port->service['extrainfo'] ?? null),
                    'cpe' => $this->nullable($cpe), 'banner' => null,
                ];
            }

            $osMatch = $host->os->osmatch[0] ?? null;
            $hostnames = [];
            foreach ($host->hostnames->hostname ?? [] as $hostname) {
                $hostnames[] = (string) $hostname['name'];
            }
            $srtt = isset($host->times['srtt']) ? (int) $host->times['srtt'] : null;
            $hosts[] = [
                'ip_address' => $addresses['ipv4'] ?: $addresses['ipv6'],
                'hostname' => isset($hostnames[0]) ? mb_substr($hostnames[0], 0, 253) : null,
                'status' => mb_substr((string) ($host->status['state'] ?? 'unknown'), 0, 20),
                'mac_address' => $addresses['mac'] ? mb_substr($addresses['mac'], 0, 17) : null,
                'vendor' => $addresses['vendor'] ? mb_substr($addresses['vendor'], 0, 255) : null,
                'operating_system' => $osMatch ? mb_substr((string) $osMatch['name'], 0, 255) : null,
                'os_accuracy' => $osMatch ? (int) $osMatch['accuracy'] : null,
                'response_time' => $srtt !== null ? (int) round($srtt / 1000) : null,
                'ports' => $ports,
                'raw_data' => ['hostnames' => $hostnames, 'reason' => (string) ($host->status['reason'] ?? '')],
            ];
        }

        return [
            'hosts' => array_values(array_filter($hosts, fn (array $host) => $host['ip_address'] !== null)),
            'duration_seconds' => isset($document->runstats->finished['elapsed']) ? (int) ceil((float) $document->runstats->finished['elapsed']) : null,
            'scanner' => ['name' => (string) ($document['scanner'] ?? 'nmap'), 'version' => (string) ($document['version'] ?? '')],
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
