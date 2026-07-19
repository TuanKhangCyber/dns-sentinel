@extends('scanner.layout')

@section('content')
<section class="scanner-hero">
    <p class="eyebrow">AUTHORIZED SECURITY ASSESSMENT</p>
    <h1>{{ __('scanner.title') }}</h1>
    <p>{{ __('scanner.disclaimer') }}</p>
</section>

<div class="scanner-grid">
    <section class="scanner-card">
        <h2>{{ __('scanner.new_scan') }}</h2>
        @unless($nmapAccess['allowed'] || $vulnerabilityAccess['allowed'])
            <div class="alert" role="status">🔒 {{ __('platform.errors.'.$nmapAccess['reason']) }} · <a href="{{ route('membership.index') }}">{{ __('platform.membership') }}</a></div>
        @endunless
        <form id="scannerCreateForm" class="scanner-form" data-store-url="{{ route('scanner.scans.store') }}">
            <label for="scannerTarget">{{ __('scanner.target') }}</label>
            <input id="scannerTarget" name="target" required placeholder="example.com / 8.8.8.8 / CIDR">

            <label for="scannerProfile">{{ __('scanner.profile') }}</label>
            <select id="scannerProfile" name="profile" required>
                @foreach($profiles as $profile)
                    @php($profileAccess = $profile['scanner_type'] === 'vulnerability' ? $vulnerabilityAccess : $nmapAccess)
                    @continue(!$profileAccess['allowed'] && !($profileAccess['visible'] ?? false))
                    <option value="{{ $profile['key'] }}" @disabled(!$profile['available'] || !$profileAccess['allowed'])>
                        {{ $profile['name'] }} @if(!$profile['available']) — {{ __('scanner.unavailable') }} @endif
                    </option>
                @endforeach
            </select>

            <div class="scanner-form-row">
                <div><label for="scannerPorts">{{ __('scanner.port_range') }}</label><input id="scannerPorts" name="port_range" placeholder="80,443,8000-8010"></div>
                <div><label for="scannerSpeed">{{ __('scanner.speed') }}</label><select id="scannerSpeed" name="speed"><option value="slow">{{ __('scanner.speed_slow') }}</option><option value="normal" selected>{{ __('scanner.speed_normal') }}</option></select></div>
                <div><label for="scannerTimeout">{{ __('scanner.timeout') }}</label><input id="scannerTimeout" name="timeout" type="number" min="10" max="3600" value="120"></div>
            </div>

            <label for="scannerSchedule">{{ __('scanner.schedule') }}</label>
            <input id="scannerSchedule" name="scheduled_at" type="datetime-local">
            <label class="authorization-check"><input name="authorization_confirmed" type="checkbox" value="1" required> <span>{{ __('scanner.authorization_confirmation') }}</span></label>
            <div id="scannerFormError" class="service-error" hidden></div>
            <button type="submit" @disabled(!$nmapAccess['allowed'] && !$vulnerabilityAccess['allowed'])>{{ __('scanner.start_scan') }}</button>
        </form>
    </section>

    <section id="currentScan" class="scanner-card" hidden>
        <div class="service-heading"><h2>{{ __('scanner.current_scan') }}</h2><span id="currentScanStatus" class="risk-badge warning">queued</span></div>
        <dl class="recon-kv-grid"><div><dt>{{ __('scanner.target') }}</dt><dd id="currentScanTarget">—</dd></div><div><dt>{{ __('scanner.stage') }}</dt><dd id="currentScanStage">—</dd></div><div><dt>{{ __('scanner.elapsed') }}</dt><dd id="currentScanElapsed">0s</dd></div></dl>
        <div class="scan-progress"><span id="currentScanProgress" style="width:0%"></span></div>
        <p id="currentScanProgressText">0%</p>
        <div class="scanner-actions"><a id="viewCurrentScan" class="button-link" aria-disabled="true">{{ __('scanner.view_results') }}</a><button id="cancelCurrentScan" type="button" class="button-danger">{{ __('scanner.cancel') }}</button></div>
        <div id="scannerSkeleton" class="skeleton-stack"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-card"></div></div>
    </section>
</div>
@endsection

@section('page-data')
<div id="scannerPageData" hidden data-page="create" data-show-base="{{ url('/scanner/scans') }}"></div>
@endsection
