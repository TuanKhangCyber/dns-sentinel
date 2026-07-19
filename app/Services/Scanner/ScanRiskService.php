<?php

namespace App\Services\Scanner;

use App\Models\Scan;

class ScanRiskService
{
    public function summarize(Scan $scan): array
    {
        $severity = $scan->findings()->selectRaw('severity, count(*) as total')->groupBy('severity')->pluck('total', 'severity')->all();

        return [
            'hosts' => $scan->hosts()->count(),
            'hosts_up' => $scan->hosts()->where('status', 'up')->count(),
            'ports' => $scan->hosts()->withCount('ports')->get()->sum('ports_count'),
            'open_ports' => $scan->hosts()->withCount(['ports as open_ports_count' => fn ($query) => $query->where('state', 'open')])->get()->sum('open_ports_count'),
            'findings' => $scan->findings()->count(),
            'severity' => $severity,
            'assessment_note' => 'Open ports and detected versions are observations, not confirmed vulnerabilities.',
        ];
    }
}
