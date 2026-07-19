@extends('scanner.layout')
@section('title', __('scanner.compare'))
@section('content')
<section class="scanner-hero compact"><h1>{{ __('scanner.compare') }}</h1><p>{{ $left->target }} ↔ {{ $right->target }}</p></section>
<section class="scanner-card">
    <div class="metrics-grid">
        @foreach(['hosts','open_ports','findings'] as $section)
            <article class="metric-card"><strong>{{ count($comparison[$section]['added']) }}</strong><span>{{ __('scanner.compare_added') }} {{ str($section)->replace('_', ' ') }}</span></article>
            <article class="metric-card"><strong>{{ count($comparison[$section]['removed']) }}</strong><span>{{ __('scanner.compare_removed') }} {{ str($section)->replace('_', ' ') }}</span></article>
        @endforeach
    </div>
</section>
@foreach(['hosts','open_ports','findings'] as $section)
<section class="scanner-card">
    <h2>{{ str($section)->replace('_', ' ')->title() }}</h2>
    <div class="table-scroll"><table class="subdomain-table"><thead><tr><th>{{ __('scanner.change') }}</th><th>{{ __('scanner.value') }}</th></tr></thead><tbody>
        @forelse($comparison[$section]['added'] as $value)<tr><td><span class="risk-badge danger">{{ __('scanner.compare_added') }}</span></td><td>{{ $value }}</td></tr>@empty @endforelse
        @forelse($comparison[$section]['removed'] as $value)<tr><td><span class="risk-badge safe">{{ __('scanner.compare_removed') }}</span></td><td>{{ $value }}</td></tr>@empty @endforelse
        @if(empty($comparison[$section]['added']) && empty($comparison[$section]['removed']))<tr><td colspan="2">{{ __('scanner.no_changes') }}</td></tr>@endif
    </tbody></table></div>
</section>
@endforeach
@endsection
