@php
    $placement = $placement ?? 'app';
    $navigationId = 'primary-navigation-'.$placement;
    $currentUser = auth()->user();
    $scannerStatus = $currentUser ? app(\App\Services\FeatureAccessService::class)->status($currentUser, 'nmap_scan') : null;
    $vulnerabilityStatus = $currentUser ? app(\App\Services\FeatureAccessService::class)->status($currentUser, 'vulnerability_scan') : null;
    $scannerVisible = ($scannerStatus['visible'] ?? false) || ($vulnerabilityStatus['visible'] ?? false);
    $scannerAllowed = ($scannerStatus['allowed'] ?? false) || ($vulnerabilityStatus['allowed'] ?? false);
    $scannerReason = ($scannerStatus['visible'] ?? false) ? $scannerStatus['reason'] : ($vulnerabilityStatus['reason'] ?? null);
@endphp
<header class="app-header">
    <a class="app-brand" href="{{ $currentUser ? route('dns.index') : route('about') }}">
        <span class="logo-frame logo-frame--nav"><img src="{{ asset('images/dns-logo.jpg') }}" alt=""></span>
        <span>{{ app(\App\Services\SystemSettingService::class)->get('site_name', config('app.name')) }}</span>
    </a>
    <button class="navigation-toggle button-secondary" type="button" data-navigation-toggle aria-controls="{{ $navigationId }}" aria-expanded="false">
        <span aria-hidden="true">☰</span><span class="sr-only">{{ __('platform.primary_navigation') }}</span>
    </button>
    <div id="{{ $navigationId }}" class="app-navigation" data-navigation-panel>
        <nav aria-label="{{ __('platform.primary_navigation') }}">
            @auth
                <a href="{{ route('dns.index') }}" @if(request()->routeIs('dns.*')) aria-current="page" @endif>DNS</a>
                @if($scannerVisible)
                    @if($scannerAllowed)
                        <a href="{{ route('scanner.index') }}" @if(request()->routeIs('scanner.*')) aria-current="page" @endif>{{ __('scanner.title') }}</a>
                    @else
                        <span class="navigation-locked" aria-disabled="true" title="{{ __('platform.errors.'.$scannerReason) }}">{{ __('scanner.title') }} <span aria-hidden="true">🔒</span></span>
                    @endif
                @endif
                <a href="{{ route('membership.index') }}" @if(request()->routeIs('membership.*')) aria-current="page" @endif>{{ __('platform.membership') }}</a>
                <a href="{{ route('credits.index') }}" @if(request()->routeIs('credits.*')) aria-current="page" @endif>{{ __('platform.credits') }}</a>
                <a href="{{ route('login-history.index') }}" @if(request()->routeIs('login-history.*')) aria-current="page" @endif><x-icon name="history" /> {{ __('ui.login_history') }}</a>
            @endauth
            <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>{{ __('platform.about') }}</a>
            <a href="{{ route('faq') }}" @if(request()->routeIs('faq')) aria-current="page" @endif>FAQ</a>
            @if($currentUser?->isAdmin())<a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>{{ __('platform.admin') }}</a>@endif
        </nav>
        <div class="app-account">
            @include('partials.current-datetime')
            @auth
                <div class="account-summary" aria-label="{{ __('platform.membership') }}">
                    <strong>{{ $currentUser->name }}</strong>
                    <span>{{ $currentUser->plan?->name ?? 'Free' }} · {{ $currentUser->wallet?->balance ?? 0 }} {{ __('platform.credits') }}</span>
                </div>
            @endauth
            @include('partials.preferences', ['placement' => $placement])
            @auth<form method="POST" action="{{ route('logout') }}">@csrf<button class="button-secondary"><x-icon name="logout" /> {{ __('ui.logout') }}</button></form>@endauth
        </div>
    </div>
</header>
