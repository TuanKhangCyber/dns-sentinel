@extends('layouts.app')
@section('title', __('platform.membership'))
@section('content')
<section class="platform-hero">
    <p class="eyebrow">MEMBERSHIP &amp; ACCESS</p>
    <h1>{{ __('platform.membership') }}</h1>
    <p>{{ __('platform.membership_intro') }}</p>
    <div class="hero-status-row">
        <span class="badge safe">{{ __('platform.current_plan') }}: {{ $current->name }}</span>
        <span class="badge unknown">{{ auth()->user()->wallet?->balance ?? 0 }} {{ __('platform.credits') }}</span>
        @if(auth()->user()->membership_expires_at)<span class="badge warning">{{ __('ui.expires_at') }}: {{ auth()->user()->membership_expires_at->translatedFormat('d/m/Y H:i') }}</span>@endif
    </div>
</section>

<div class="pricing-grid">
    @foreach($plans as $plan)
        <article class="platform-card pricing-card @if($current->is($plan)) current @endif">
            <header class="card-heading"><div><p class="eyebrow">{{ strtoupper($plan->code) }}</p><h2>{{ $plan->name }}</h2></div>@if($current->is($plan))<span class="badge safe">{{ __('platform.current_plan') }}</span>@endif</header>
            <p>{{ $plan->description }}</p>
            <dl class="plan-metrics"><div><dt>{{ __('platform.monthly_credits') }}</dt><dd>{{ $plan->monthly_credit_allowance }}</dd></div><div><dt>{{ __('platform.history_days') }}</dt><dd>{{ $plan->history_retention_days }}</dd></div></dl>
            <ul class="feature-list">@foreach($plan->features as $feature)<li><span aria-hidden="true">✓</span><span>{{ $feature->name }} @if(($feature->pivot->credit_cost_override ?? $feature->credit_cost)>0)<small>{{ $feature->pivot->credit_cost_override ?? $feature->credit_cost }} {{ __('platform.credits') }}</small>@endif</span></li>@endforeach</ul>
            @unless($current->is($plan))<form method="POST" action="{{ route('membership.request-upgrade') }}" data-submit-lock>@csrf<button>{{ __('platform.request_upgrade') }}</button></form>@endunless
        </article>
    @endforeach
</div>

<section class="platform-card">
    <header class="card-heading"><div><p class="eyebrow">FEATURE FLAGS</p><h2>{{ __('platform.feature_status') }}</h2></div></header>
    <div class="feature-status-grid">
        @foreach($statuses as $code => $status)
            <article class="feature-state @unless($status['allowed']) locked @endunless" data-feature-code="{{ $code }}" @unless($status['allowed']) aria-disabled="true" @endunless>
                <div><strong>{{ str($code)->replace('_', ' ')->title() }}</strong>@if($status['cost'])<small>{{ $status['cost'] }} {{ __('platform.credits') }}</small>@endif</div>
                <span class="badge {{ $status['allowed'] ? 'safe' : ($status['reason'] === 'service_unavailable' ? 'unknown' : 'warning') }}">{{ $status['allowed'] ? __('platform.available') : __('platform.errors.'.$status['reason']) }}</span>
            </article>
        @endforeach
    </div>
</section>
@endsection
