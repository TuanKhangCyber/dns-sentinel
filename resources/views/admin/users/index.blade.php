@extends('admin.layout')
@section('title', __('platform.users'))
@section('admin-content')
<x-admin.page-header :title="__('platform.users')" :description="__('platform.users_intro')" :eyebrow="__('platform.user_management')" />

<form class="admin-filter-bar" method="GET" action="{{ route('admin.users.index') }}">
    <label class="admin-search-field"><span>{{ __('platform.search') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('platform.email_or_name') }}"></label>
    <label><span>{{ __('platform.plan') }}</span><select name="plan"><option value="">{{ __('platform.all') }}</option>@foreach($plans as $plan)<option value="{{ $plan->code }}" @selected(request('plan')===$plan->code)>{{ $plan->name }}</option>@endforeach</select></label>
    <label><span>{{ __('platform.status') }}</span><select name="status"><option value="">{{ __('platform.all') }}</option>@foreach(__('platform.account_statuses') as $code => $label)<option value="{{ $code }}" @selected(request('status')===$code)>{{ $label }}</option>@endforeach</select></label>
    <label><span>{{ __('platform.role') }}</span><select name="role"><option value="">{{ __('platform.all') }}</option>@foreach(__('platform.roles') as $code => $label)<option value="{{ $code }}" @selected(request('role')===$code)>{{ $label }}</option>@endforeach</select></label>
    <div class="filter-actions"><button><x-icon name="users" /> {{ __('platform.filter') }}</button><a class="button-link button-secondary" href="{{ route('admin.users.index') }}">{{ __('platform.reset') }}</a></div>
</form>

<section class="admin-panel admin-table-panel">
    <div class="table-wrap"><table class="admin-table"><thead><tr><th scope="col">{{ __('platform.user') }}</th><th scope="col">{{ __('platform.plan') }}</th><th scope="col">{{ __('platform.role') }}</th><th scope="col">{{ __('platform.status') }}</th><th scope="col">{{ __('platform.credits') }}</th><th scope="col">{{ __('platform.usage') }}</th><th scope="col"><span class="sr-only">{{ __('platform.actions') }}</span></th></tr></thead>
    <tbody>@forelse($users as $user)<tr>
        <td data-label="{{ __('platform.user') }}"><div class="admin-user-cell"><span class="user-avatar">{{ str($user->name)->substr(0, 1)->upper() }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span></div></td>
        <td data-label="{{ __('platform.plan') }}">{{ $user->plan?->name ?? '—' }}</td>
        <td data-label="{{ __('platform.role') }}"><span class="status-badge {{ $user->role === 'admin' ? 'info' : 'unknown' }}">{{ __('platform.roles')[$user->role] ?? $user->role }}</span></td>
        <td data-label="{{ __('platform.status') }}"><span class="status-badge {{ $user->status === 'active' ? 'safe' : 'warning' }}">{{ __('platform.account_statuses')[$user->status] ?? $user->status }}</span></td>
        <td data-label="{{ __('platform.credits') }}">{{ number_format($user->wallet?->balance ?? 0) }}</td>
        <td data-label="{{ __('platform.usage') }}">{{ $user->scans_count }} / {{ $user->recon_histories_count }}</td>
        <td data-label="{{ __('platform.actions') }}"><a class="button-link button-secondary" href="{{ route('admin.users.show', $user) }}">{{ __('platform.manage') }}</a></td>
    </tr>@empty<tr class="admin-table-empty"><td colspan="7"><x-admin.empty-state /></td></tr>@endforelse</tbody></table></div>
    <div class="admin-pagination">{{ $users->links() }}</div>
</section>
@endsection
