@extends('admin.layout')
@section('title', __('platform.dashboard'))
@section('admin-content')
<x-admin.page-header :title="__('platform.dashboard')" :description="__('platform.admin_dashboard_intro')" :eyebrow="__('platform.system_overview')">
    <x-slot:actions><a class="button-link" href="{{ route('admin.users.index') }}"><x-icon name="users" /> {{ __('platform.manage_users') }}</a></x-slot:actions>
</x-admin.page-header>

<section class="admin-stat-grid" aria-label="{{ __('platform.system_overview') }}">
    <article class="admin-stat-card" data-tone="blue">
        <div class="admin-stat-heading"><span><x-icon name="users" /></span><p>{{ __('platform.metrics.users') }}</p></div>
        <strong>{{ number_format((int) $metrics['users']) }}</strong>
        <dl class="admin-stat-details">
            <div><dt>{{ __('platform.metrics.free_users') }}</dt><dd>{{ number_format((int) $metrics['free_users']) }}</dd></div>
            <div><dt>{{ __('platform.metrics.plus_users') }}</dt><dd>{{ number_format((int) $metrics['plus_users']) }}</dd></div>
            <div><dt>{{ __('platform.metrics.active_users') }}</dt><dd>{{ number_format((int) $metrics['active_users']) }}</dd></div>
            <div><dt>{{ __('platform.metrics.suspended_users') }}</dt><dd>{{ number_format((int) $metrics['suspended_users']) }}</dd></div>
            <div><dt>{{ __('platform.metrics.admins') }}</dt><dd>{{ number_format((int) $metrics['admins']) }}</dd></div>
        </dl>
    </article>
    <article class="admin-stat-card" data-tone="emerald">
        <div class="admin-stat-heading"><span><x-icon name="credits" /></span><p>{{ __('platform.metrics.current_credits') }}</p></div>
        <strong>{{ number_format((int) $metrics['current_credits']) }}</strong>
        <dl class="admin-stat-details">
            <div><dt>{{ __('platform.metrics.credits_granted') }}</dt><dd>{{ number_format((int) $metrics['credits_granted']) }}</dd></div>
            <div><dt>{{ __('platform.metrics.credits_used') }}</dt><dd>{{ number_format((int) $metrics['credits_used']) }}</dd></div>
        </dl>
    </article>
    <article class="admin-stat-card" data-tone="violet">
        <div class="admin-stat-heading"><span><x-icon name="features" /></span><p>{{ __('platform.features') }}</p></div>
        <strong>{{ number_format((int) ($metrics['features_enabled'] + $metrics['features_disabled'])) }}</strong>
        <dl class="admin-stat-details">
            <div><dt>{{ __('platform.metrics.features_enabled') }}</dt><dd>{{ number_format((int) $metrics['features_enabled']) }}</dd></div>
            <div><dt>{{ __('platform.metrics.features_disabled') }}</dt><dd>{{ number_format((int) $metrics['features_disabled']) }}</dd></div>
        </dl>
    </article>
    <article class="admin-stat-card" data-tone="amber">
        <div class="admin-stat-heading"><span><x-icon name="dashboard" /></span><p>{{ __('platform.metrics.scans') }}</p></div>
        <strong>{{ number_format((int) $metrics['scans']) }}</strong>
    </article>
</section>

<div class="admin-dashboard-grid">
    <section class="admin-panel">
        <div class="admin-section-heading"><div><p class="eyebrow">{{ __('platform.health') }}</p><h2>{{ __('platform.service_status') }}</h2></div><a href="{{ route('admin.settings.index') }}">{{ __('platform.settings') }}</a></div>
        <div class="admin-health-list">
            @foreach($serviceStatuses as $service => $ready)
                <div><span class="health-dot {{ $ready ? 'ready' : 'missing' }}"></span><strong>{{ $service }}</strong><span class="status-badge {{ $ready ? 'safe' : 'warning' }}">{{ $ready ? __('platform.configured') : __('platform.missing_configuration') }}</span></div>
            @endforeach
        </div>
    </section>
    <section class="admin-panel">
        <div class="admin-section-heading"><div><p class="eyebrow">{{ __('platform.scans') }}</p><h2>{{ __('platform.scan_status') }}</h2></div></div>
        <div class="admin-status-cloud">
            @forelse($scanStatuses as $status => $total)<span class="status-badge unknown">{{ __('platform.scan_statuses')[$status] ?? str($status)->replace('_', ' ')->title() }} <strong>{{ $total }}</strong></span>@empty<x-admin.empty-state />@endforelse
        </div>
    </section>
</div>

<div class="admin-dashboard-grid">
    <section class="admin-panel">
        <div class="admin-section-heading"><div><p class="eyebrow">{{ __('platform.audit_logs') }}</p><h2>{{ __('platform.recent_admin_actions') }}</h2></div><a href="{{ route('admin.audit.index') }}">{{ __('platform.view_all') }}</a></div>
        <div class="admin-activity-list">
            @forelse($recentAudits as $audit)
                <article><span class="activity-icon"><x-icon name="audit" /></span><div><strong>{{ $audit->action }}</strong><p>{{ $audit->actor?->name ?? __('platform.system') }} · {{ $audit->created_at->diffForHumans() }}</p></div></article>
            @empty<x-admin.empty-state />@endforelse
        </div>
    </section>
    <section class="admin-panel">
        <div class="admin-section-heading"><div><p class="eyebrow">{{ __('platform.users') }}</p><h2>{{ __('platform.recent_users') }}</h2></div><a href="{{ route('admin.users.index') }}">{{ __('platform.view_all') }}</a></div>
        <div class="admin-user-list">
            @forelse($recentUsers as $user)
                <a href="{{ route('admin.users.show', $user) }}"><span class="user-avatar">{{ str($user->name)->substr(0, 1)->upper() }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span><span class="status-badge {{ $user->status === 'active' ? 'safe' : 'warning' }}">{{ $user->plan?->name ?? '—' }}</span></a>
            @empty<x-admin.empty-state />@endforelse
        </div>
    </section>
</div>
@endsection
