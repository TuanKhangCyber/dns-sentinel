@extends('scanner.layout')
@section('title', __('scanner.history'))

@section('content')
<section class="scanner-hero compact"><h1>{{ __('scanner.history') }}</h1></section>
<section class="scanner-card">
    <form class="history-filters" method="GET">
        <input name="target" value="{{ request('target') }}" placeholder="{{ __('scanner.target') }}">
        <select name="profile"><option value="">{{ __('scanner.all_profiles') }}</option>@foreach($profiles as $profile)<option value="{{ $profile['key'] }}" @selected(request('profile') === $profile['key'])>{{ $profile['name'] }}</option>@endforeach</select>
        <input type="date" name="date_from" value="{{ request('date_from') }}"><input type="date" name="date_to" value="{{ request('date_to') }}">
        <select name="severity"><option value="">{{ __('scanner.all_severities') }}</option>@foreach(['critical','high','medium','low','informational'] as $severity)<option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>@endforeach</select>
        <button type="submit">{{ __('scanner.filter') }}</button>
    </form>
    <form id="compareScansForm" action="{{ route('scanner.compare') }}" method="GET">
        <div class="table-scroll"><table class="subdomain-table"><thead><tr><th>{{ __('scanner.compare') }}</th><th>{{ __('scanner.target') }}</th><th>{{ __('scanner.profile') }}</th><th>{{ __('scanner.status') }}</th><th>{{ __('scanner.created_at') }}</th><th>{{ __('scanner.actions') }}</th></tr></thead><tbody>
        @forelse($scans as $scan)
            <tr><td><input type="checkbox" name="selected_scan" value="{{ $scan->id }}" data-compare-scan></td><td>{{ $scan->target }}</td><td>{{ $scan->profile }}</td><td><span class="risk-badge {{ $scan->status === 'completed' ? 'safe' : ($scan->status === 'failed' ? 'danger' : 'warning') }}">{{ $scan->status }}</span></td><td>{{ $scan->created_at }}</td><td><div class="table-actions"><a href="{{ route('scanner.show', $scan) }}">{{ __('scanner.view') }}</a>@if($scan->isTerminal())<button type="button" data-rerun-url="{{ route('scanner.scans.rerun', $scan) }}">{{ __('scanner.rerun') }}</button><button type="button" class="button-danger" data-delete-url="{{ route('scanner.scans.destroy', $scan) }}">{{ __('scanner.delete') }}</button>@else<span class="badge warning" aria-disabled="true">{{ $scan->status }}</span>@endif</div></td></tr>
        @empty<tr><td colspan="6">{{ __('scanner.no_scans') }}</td></tr>@endforelse
        </tbody></table></div><button type="submit" id="compareButton" disabled>{{ __('scanner.compare_selected') }}</button>
    </form>
    {{ $scans->links() }}
</section>
@endsection
@section('page-data')<div id="scannerPageData" hidden data-page="history" data-compare-url="{{ route('scanner.compare') }}" data-delete-confirm="{{ __('scanner.delete_confirm') }}"></div>@endsection
