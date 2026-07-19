@extends('admin.layout')
@section('title', 'Admin Dashboard')
@section('admin-content')<h1>Admin Dashboard</h1><div class="metric-grid">@foreach($metrics as $label => $value)<article class="metric-card"><strong>{{ $value }}</strong><span>{{ str($label)->replace('_', ' ')->title() }}</span></article>@endforeach</div><section class="platform-card"><h2>Scan status</h2>@forelse($scanStatuses as $status => $total)<span class="badge unknown">{{ $status }}: {{ $total }}</span>@empty<p>{{ __('platform.no_items') }}</p>@endforelse</section>@endsection
