<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('platform.admin')) — {{ config('app.name') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/dns-logo.jpg') }}">
    @include('partials.theme-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="platform-page admin-page">
<div class="admin-shell" data-admin-shell>
    <button class="admin-drawer-toggle button-secondary" type="button" data-admin-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false">
        <x-icon name="menu" /><span>{{ __('platform.admin_menu') }}</span>
    </button>
    <button class="admin-sidebar-backdrop" type="button" data-admin-sidebar-close aria-label="{{ __('platform.close') }}" hidden></button>

    <aside id="admin-sidebar" class="admin-sidebar" data-admin-sidebar aria-label="{{ __('platform.admin_navigation') }}">
        <div class="admin-sidebar-brand">
            <span class="logo-frame logo-frame--nav"><img src="{{ asset('images/dns-logo.jpg') }}" alt=""></span>
            <span><strong>{{ config('app.name') }}</strong><small>{{ __('platform.admin_console') }}</small></span>
            <button class="admin-sidebar-close button-secondary" type="button" data-admin-sidebar-close aria-label="{{ __('platform.close') }}"><x-icon name="close" /></button>
        </div>
        <nav>
            <x-admin.nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="dashboard">{{ __('platform.dashboard') }}</x-admin.nav-link>
            <x-admin.nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="users">{{ __('platform.users') }}</x-admin.nav-link>
            <x-admin.nav-link :href="route('admin.plans.index')" :active="request()->routeIs('admin.plans.*')" icon="plans">{{ __('platform.plans') }}</x-admin.nav-link>
            <x-admin.nav-link :href="route('admin.features.index')" :active="request()->routeIs('admin.features.*')" icon="features">{{ __('platform.features') }}</x-admin.nav-link>
            <x-admin.nav-link :href="route('admin.credits.index')" :active="request()->routeIs('admin.credits.*')" icon="credits">{{ __('platform.credit_ledger') }}</x-admin.nav-link>
            <x-admin.nav-link :href="route('admin.content.index')" :active="request()->routeIs('admin.content.*') || request()->routeIs('admin.faqs.*')" icon="content">{{ __('platform.about_faq') }}</x-admin.nav-link>
            <x-admin.nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')" icon="settings">{{ __('platform.settings') }}</x-admin.nav-link>
            <x-admin.nav-link :href="route('admin.audit.index')" :active="request()->routeIs('admin.audit.*')" icon="audit">{{ __('platform.audit_logs') }}</x-admin.nav-link>
        </nav>
        <div class="admin-sidebar-footer">
            <div class="admin-identity"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
            <a class="admin-back-link" href="{{ route('dns.index') }}"><x-icon name="arrow-left" /> {{ __('platform.back_to_app') }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="button-secondary" type="submit"><x-icon name="logout" /> {{ __('ui.logout') }}</button></form>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="admin-breadcrumb"><span>{{ __('platform.admin') }}</span><span aria-hidden="true">/</span><strong>@yield('title', __('platform.dashboard'))</strong></div>
            <div class="admin-topbar-actions">
                @include('partials.current-datetime')
                @include('partials.preferences', ['placement' => 'admin'])
            </div>
        </header>

        <div class="admin-content">
            @if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
            @yield('admin-content')
        </div>
    </main>
</div>

<dialog class="confirmation-dialog" data-confirm-dialog aria-labelledby="confirmation-title" aria-describedby="confirmation-message">
    <form method="dialog">
        <div class="confirmation-icon"><x-icon name="audit" /></div>
        <h2 id="confirmation-title" data-confirm-title data-default-text="{{ __('platform.confirm_action') }}">{{ __('platform.confirm_action') }}</h2>
        <p id="confirmation-message" data-confirm-message></p>
        <div class="confirmation-actions">
            <button class="button-secondary" value="cancel" data-confirm-cancel>{{ __('platform.cancel') }}</button>
            <button class="button-danger" value="confirm" data-confirm-accept>{{ __('platform.confirm') }}</button>
        </div>
    </form>
</dialog>
</body>
</html>
