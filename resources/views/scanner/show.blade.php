@extends('scanner.layout')
@section('title', __('scanner.scan_results').' #'.$scan->id)

@section('content')
<section class="scanner-hero compact">
    <p class="eyebrow">SCAN #{{ $scan->id }}</p><h1>{{ $scan->target }}</h1>
    @php($scanExports = collect(['json' => 'export_json', 'csv' => 'export_csv', 'pdf' => 'export_pdf'])->map(fn($code) => app(\App\Services\FeatureAccessService::class)->status(auth()->user(), $code)))
    <div class="scanner-actions"><span id="scanStatusBadge" class="risk-badge warning">{{ $scan->status }}</span><a class="button-link" href="{{ route('scanner.history') }}">{{ __('scanner.history') }}</a>@foreach($scanExports as $format => $access)@if($access['visible'])@if($access['allowed'])<a class="button-link" href="{{ route('scanner.scans.export.'.$format, $scan) }}">{{ strtoupper($format) }}</a>@else<span class="badge warning" aria-disabled="true">{{ strtoupper($format) }} · {{ __('platform.errors.'.$access['reason']) }}</span>@endif @endif @endforeach @if(!$scan->isTerminal())<button id="cancelShowScan" type="button" class="button-danger">{{ __('scanner.cancel') }}</button>@endif</div>
    <div class="scan-progress"><span id="scanProgress" style="width:{{ $scan->progress }}%"></span></div>
    <div id="scanRuntimeError" class="service-error" hidden></div>
</section>

<nav class="result-tabs scanner-tabs" aria-label="Scanner results">
    @foreach(['overview','hosts','ports','operating_systems','vulnerabilities','cves','remediation','evidence','configuration'] as $tab)
        <button type="button" class="tab-button @if($loop->first) active @endif" data-scanner-tab="{{ $tab }}">{{ __('scanner.tabs.'.$tab) }}</button>
    @endforeach
</nav>

<section class="scanner-card scanner-result-panel active" data-scanner-panel="overview"><div id="scanOverview" class="skeleton-stack"><div class="skeleton skeleton-card"></div></div></section>
<section class="scanner-card scanner-result-panel" data-scanner-panel="hosts"><input id="hostSearch" class="table-filter" placeholder="{{ __('scanner.search_hosts') }}"><div id="scanHosts" class="table-scroll"></div></section>
<section class="scanner-card scanner-result-panel" data-scanner-panel="ports"><div class="scanner-filters"><select id="portStateFilter"><option value="">{{ __('scanner.all_states') }}</option><option>open</option><option>closed</option><option>filtered</option></select><select id="portProtocolFilter"><option value="">{{ __('scanner.all_protocols') }}</option><option>tcp</option><option>udp</option></select><input id="portSearch" placeholder="{{ __('scanner.search_services') }}"></div><div id="scanPorts" class="table-scroll"></div></section>
<section class="scanner-card scanner-result-panel" data-scanner-panel="operating_systems"><div id="scanOperatingSystems"></div></section>
<section class="scanner-card scanner-result-panel" data-scanner-panel="vulnerabilities"><div class="scanner-filters"><select id="findingSeverityFilter"><option value="">{{ __('scanner.all_severities') }}</option>@foreach(['critical','high','medium','low','informational'] as $severity)<option value="{{ $severity }}">{{ ucfirst($severity) }}</option>@endforeach</select><select id="findingStatusFilter"><option value="">{{ __('scanner.all_finding_statuses') }}</option><option value="potential">Potential</option><option value="open">Open</option><option value="passed">Passed</option></select><input id="findingSearch" placeholder="CVE / host / port / title"></div><div id="scanFindings" class="table-scroll"></div></section>
<section class="scanner-card scanner-result-panel" data-scanner-panel="cves"><div id="scanCves"></div></section>
<section class="scanner-card scanner-result-panel" data-scanner-panel="remediation"><div id="scanRemediation"></div></section>
<section class="scanner-card scanner-result-panel" data-scanner-panel="evidence"><pre id="scanEvidence" class="scanner-evidence"></pre></section>
<section class="scanner-card scanner-result-panel" data-scanner-panel="configuration"><dl class="recon-kv-grid"><div><dt>{{ __('scanner.profile') }}</dt><dd>{{ $scan->profile }}</dd></div><div><dt>{{ __('scanner.target_type') }}</dt><dd>{{ $scan->target_type }}</dd></div><div><dt>{{ __('scanner.scanner_type') }}</dt><dd>{{ $scan->scanner_type }}</dd></div><div><dt>{{ __('scanner.options') }}</dt><dd>{{ json_encode(collect($scan->options ?? [])->except(['requested_ip', 'remote_scan_id'])->all(), JSON_UNESCAPED_UNICODE) }}</dd></div></dl></section>
@endsection

@section('page-data')
<div id="scannerPageData" hidden data-page="show" data-scan-id="{{ $scan->id }}" data-status-url="{{ route('scanner.scans.status', $scan) }}" data-results-url="{{ route('scanner.scans.results', $scan) }}" data-cancel-url="{{ route('scanner.scans.cancel', $scan) }}"></div>
@endsection
