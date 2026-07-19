<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 42px 36px 54px; }
        body { color: #172033; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
        h1 { margin: 0; color: #0f172a; font-size: 22px; }
        h2 { padding-bottom: 5px; margin: 22px 0 8px; color: #1d4ed8; font-size: 14px; border-bottom: 1px solid #cbd5e1; }
        .header { padding-bottom: 16px; border-bottom: 3px solid #2563eb; }
        .meta { margin-top: 8px; color: #64748b; }
        .score { display: inline-block; padding: 5px 9px; margin-top: 8px; font-weight: bold; background: #dbeafe; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px; text-align: left; vertical-align: top; border: 1px solid #dbe2ea; }
        th { width: 26%; background: #f1f5f9; }
        .badge { padding: 3px 6px; color: #fff; font-weight: bold; border-radius: 3px; }
        .safe { background: #15803d; } .warning { background: #ca8a04; } .danger, .error { background: #b91c1c; }
        pre { padding: 8px; overflow-wrap: anywhere; white-space: pre-wrap; background: #f8fafc; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <header class="header">
        <h1>{{ $company }}</h1>
        <div class="meta">{{ __('ui.report_title') }} | {{ $history->target }}</div>
        <div class="meta">{{ __('ui.generated_at') }}: {{ $generatedAt->format('Y-m-d H:i:s T') }}</div>
        <div class="score">{{ __('ui.overall_score') }}: {{ $history->overall_score ?? 'N/A' }}/100</div>
    </header>

    @forelse (($history->results ?? []) as $service => $result)
        <section>
            <h2>{{ str($service)->replace('_', ' ')->title() }}</h2>
            <table>
                <tr><th>{{ __('ui.rdap_status') }}</th><td><span class="badge {{ $result['status'] ?? 'error' }}">{{ strtoupper($result['status'] ?? 'unknown') }}</span></td></tr>
                <tr><th>{{ __('ui.score') }}</th><td>{{ $result['score'] ?? 'N/A' }}</td></tr>
                <tr><th>{{ __('ui.checked_at') }}</th><td>{{ $result['checked_at'] ?? 'N/A' }}</td></tr>
                @if (!empty($result['warnings']))
                    <tr><th>{{ __('ui.warnings') }}</th><td>{{ implode(', ', $result['warnings']) }}</td></tr>
                @endif
            </table>
            <pre>{{ json_encode($result['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </section>
    @empty
        <p>{{ __('ui.no_scan_results') }}</p>
    @endforelse
</body>
</html>
