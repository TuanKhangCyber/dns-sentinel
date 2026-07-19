<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 42px 34px 56px; }
        body { color: #172033; font-family: "DejaVu Sans", sans-serif; font-size: 9px; line-height: 1.45; }
        h1 { margin: 0; color: #0f172a; font-size: 21px; } h2 { padding-bottom: 5px; margin: 20px 0 8px; color: #155e75; font-size: 13px; border-bottom: 1px solid #cbd5e1; }
        .header { padding-bottom: 14px; border-bottom: 3px solid #0891b2; } .logo { float: right; padding: 8px 10px; color: white; font-weight: bold; background: #0f172a; border-radius: 4px; }
        .meta { margin-top: 5px; color: #64748b; } .summary { margin-top: 12px; } .metric { display: inline-block; width: 17%; padding: 7px; margin-right: 3px; background: #ecfeff; border: 1px solid #a5f3fc; }
        .metric strong { display: block; color: #0e7490; font-size: 15px; } table { width: 100%; border-collapse: collapse; page-break-inside: auto; } tr { page-break-inside: avoid; }
        th, td { padding: 5px; text-align: left; vertical-align: top; border: 1px solid #dbe2ea; overflow-wrap: anywhere; } th { color: #334155; background: #f1f5f9; }
        .badge { padding: 2px 5px; color: #fff; font-weight: bold; border-radius: 2px; } .critical, .high { background: #b91c1c; } .medium { background: #c2410c; } .low { color: #422006; background: #facc15; } .informational { background: #2563eb; }
        .finding { margin-bottom: 10px; padding: 8px; border: 1px solid #dbe2ea; page-break-inside: avoid; } .finding-title { margin-bottom: 5px; font-weight: bold; }
        .disclaimer { padding: 8px; margin-top: 20px; color: #475569; background: #f8fafc; border-left: 3px solid #64748b; }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">DNS RS</div><h1>{{ $report['company'] }}</h1>
        <div class="meta">{{ __('scanner.report_title') }} #{{ $report['scan']['id'] }} | {{ $report['scan']['target'] }}</div>
        <div class="meta">{{ __('scanner.generated_at') }}: {{ $report['generated_at'] }} | {{ __('scanner.performed_by') }}: {{ $report['scan']['performed_by']['name'] ?? 'N/A' }}</div>
    </header>
    @php($summary = $report['scan']['summary'])
    <section class="summary">
        @foreach(['hosts','hosts_up','open_ports','findings'] as $metric)<div class="metric"><strong>{{ $summary[$metric] ?? 0 }}</strong>{{ str($metric)->replace('_', ' ')->title() }}</div>@endforeach
    </section>

    <h2>{{ __('scanner.executive_summary') }}</h2>
    <table><tr><th>{{ __('scanner.target') }}</th><td>{{ $report['scan']['target'] }}</td><th>{{ __('scanner.profile') }}</th><td>{{ $report['scan']['profile'] }}</td></tr><tr><th>{{ __('scanner.status') }}</th><td>{{ $report['scan']['status'] }}</td><th>{{ __('scanner.duration') }}</th><td>{{ $report['scan']['duration_seconds'] ?? 'N/A' }}s</td></tr></table>

    <h2>{{ __('scanner.host_discovery') }}</h2>
    <table><thead><tr><th>IP</th><th>Hostname</th><th>Status</th><th>OS</th></tr></thead><tbody>@forelse($report['hosts'] as $host)<tr><td>{{ $host['ip_address'] }}</td><td>{{ $host['hostname'] }}</td><td>{{ $host['status'] }}</td><td>{{ $host['operating_system'] }}</td></tr>@empty<tr><td colspan="4">{{ __('scanner.no_results') }}</td></tr>@endforelse</tbody></table>

    <h2>{{ __('scanner.open_ports') }}</h2>
    <table><thead><tr><th>Host</th><th>Port</th><th>Protocol</th><th>Service</th><th>Product / Version</th></tr></thead><tbody>@php($hasPorts = false)@foreach($report['hosts'] as $host)@foreach($host['ports'] as $port)@if($port['state'] === 'open')@php($hasPorts = true)<tr><td>{{ $host['ip_address'] }}</td><td>{{ $port['port'] }}</td><td>{{ $port['protocol'] }}</td><td>{{ $port['service'] }}</td><td>{{ trim(($port['product'] ?? '').' '.($port['version'] ?? '')) }}</td></tr>@endif @endforeach @endforeach @unless($hasPorts)<tr><td colspan="5">{{ __('scanner.no_results') }}</td></tr>@endunless</tbody></table>

    <h2>{{ __('scanner.finding_details') }}</h2>
    @forelse($report['findings'] as $finding)<article class="finding"><div class="finding-title"><span class="badge {{ $finding['severity'] }}">{{ strtoupper($finding['severity']) }}</span> {{ $finding['title'] }}</div><div><strong>Host:</strong> {{ $finding['host'] ?? 'N/A' }} | <strong>Port:</strong> {{ $finding['port'] ?? 'N/A' }} | <strong>CVE:</strong> {{ implode(', ', $finding['cve']) ?: 'N/A' }}</div>@if($finding['description'])<p>{{ $finding['description'] }}</p>@endif @if($finding['evidence'])<p><strong>{{ __('scanner.evidence_label') }}:</strong> {{ $finding['evidence'] }}</p>@endif @if($finding['solution'])<p><strong>{{ __('scanner.remediation_label') }}:</strong> {{ $finding['solution'] }}</p>@endif</article>@empty<p>{{ __('scanner.no_results') }}</p>@endforelse

    <h2>{{ __('scanner.scan_configuration') }}</h2><table>@foreach($report['scan']['configuration'] as $key => $value)<tr><th>{{ str($key)->replace('_', ' ')->title() }}</th><td>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($value ?? 'N/A') }}</td></tr>@endforeach</table>
    <div class="disclaimer"><strong>Disclaimer:</strong> {{ $report['disclaimer'] }}</div>
</body>
</html>
