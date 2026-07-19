<?php

namespace App\Services\Scanner;

use App\Models\Scan;
use Illuminate\Support\Collection;

class ScanComparisonService
{
    public function compare(Scan $left, Scan $right): array
    {
        $left->loadMissing(['hosts.ports', 'findings.host:id,ip_address', 'findings.port:id,port,protocol']);
        $right->loadMissing(['hosts.ports', 'findings.host:id,ip_address', 'findings.port:id,port,protocol']);

        return [
            'hosts' => $this->diff($this->hosts($left), $this->hosts($right)),
            'open_ports' => $this->diff($this->ports($left), $this->ports($right)),
            'findings' => $this->diff($this->findings($left), $this->findings($right)),
        ];
    }

    private function hosts(Scan $scan): Collection
    {
        return $scan->hosts->map(fn ($host) => (string) ($host->ip_address ?: $host->hostname))->filter()->unique()->sort()->values();
    }

    private function ports(Scan $scan): Collection
    {
        return $scan->hosts->flatMap(fn ($host) => $host->ports->where('state', 'open')->map(
            fn ($port) => sprintf('%s:%s/%d', $host->ip_address ?: $host->hostname, $port->protocol, $port->port)
        ))->unique()->sort()->values();
    }

    private function findings(Scan $scan): Collection
    {
        return $scan->findings->map(fn ($finding) => implode(' | ', array_filter([
            $finding->severity, $finding->plugin_id ?: $finding->title,
            $finding->host?->ip_address, $finding->port ? $finding->port->protocol.'/'.$finding->port->port : null,
        ])))->unique()->sort()->values();
    }

    private function diff(Collection $left, Collection $right): array
    {
        return [
            'added' => $right->diff($left)->values()->all(),
            'removed' => $left->diff($right)->values()->all(),
            'unchanged' => $left->intersect($right)->values()->all(),
        ];
    }
}
