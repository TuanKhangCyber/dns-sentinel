@extends('admin.layout')
@section('title', $managedUser->name)
@section('admin-content')
<x-admin.page-header :title="$managedUser->name" :description="$managedUser->email" :eyebrow="__('platform.user_management')">
    <x-slot:actions><a class="button-link button-secondary" href="{{ route('admin.users.index') }}"><x-icon name="arrow-left" /> {{ __('platform.back') }}</a></x-slot:actions>
</x-admin.page-header>

<section class="admin-profile-summary">
    <article><span>{{ __('platform.plan') }}</span><strong>{{ $managedUser->plan?->name ?? '—' }}</strong></article>
    <article><span>{{ __('platform.balance') }}</span><strong>{{ number_format($managedUser->wallet?->balance ?? 0) }}</strong></article>
    <article><span>{{ __('platform.scans') }}</span><strong>{{ $managedUser->scans()->count() }}</strong></article>
    <article><span>{{ __('platform.recon_lookups') }}</span><strong>{{ $managedUser->reconHistories()->count() }}</strong></article>
</section>

<div class="admin-form-columns">
    <form class="admin-panel admin-form" method="POST" action="{{ route('admin.users.update', $managedUser) }}" data-confirm="{{ __('platform.confirm_user_update') }}" data-confirm-title="{{ __('platform.update_user_access') }}" data-submit-lock>
        @csrf @method('PUT')
        <div class="admin-section-heading"><div><p class="eyebrow">{{ __('platform.access') }}</p><h2>{{ __('platform.membership') }}</h2></div></div>
        <div class="form-section-grid">
            <label>{{ __('platform.plan') }}<select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((int) old('plan_id', $managedUser->plan_id)===$plan->id)>{{ $plan->name }}</option>@endforeach</select>@error('plan_id')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label>{{ __('platform.role') }}<select name="role" required>@foreach(__('platform.roles') as $code => $label)<option value="{{ $code }}" @selected(old('role', $managedUser->role)===$code)>{{ $label }}</option>@endforeach</select>@error('role')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label>{{ __('platform.status') }}<select name="status" required>@foreach(__('platform.account_statuses') as $code => $label)<option value="{{ $code }}" @selected(old('status', $managedUser->status)===$code)>{{ $label }}</option>@endforeach</select>@error('status')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label>{{ __('platform.expiry') }}<input type="datetime-local" name="membership_expires_at" value="{{ old('membership_expires_at', $managedUser->membership_expires_at?->format('Y-m-d\TH:i')) }}">@error('membership_expires_at')<small class="field-error">{{ $message }}</small>@enderror</label>
        </div>
        <p class="form-hint">{{ __('platform.last_admin_hint') }}</p>
        <div class="sticky-form-actions"><button><x-icon name="users" /> {{ __('platform.save') }}</button></div>
    </form>

    <form class="admin-panel admin-form" method="POST" action="{{ route('admin.users.credits', $managedUser) }}" data-confirm="{{ __('platform.confirm_credit_adjustment') }}" data-confirm-title="{{ __('platform.adjust_credits') }}" data-submit-lock data-credit-adjustment data-current-balance="{{ $managedUser->wallet?->balance ?? 0 }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div class="admin-section-heading"><div><p class="eyebrow">{{ __('platform.credit_wallet') }}</p><h2>{{ __('platform.adjust_credits') }}</h2></div></div>
        <div class="credit-preview"><span>{{ __('platform.balance') }}</span><strong>{{ number_format($managedUser->wallet?->balance ?? 0) }}</strong><span aria-hidden="true">→</span><strong data-credit-preview>{{ number_format($managedUser->wallet?->balance ?? 0) }}</strong></div>
        <label>{{ __('platform.amount') }}<input name="amount" type="number" required min="-1000000" max="1000000" step="1">@error('amount')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>{{ __('platform.reason') }}<textarea name="reason" required minlength="5" maxlength="1000">{{ old('reason') }}</textarea>@error('reason')<small class="field-error">{{ $message }}</small>@enderror</label>
        <p class="form-hint">{{ __('platform.credit_adjustment_hint') }}</p>
        <div class="sticky-form-actions"><button><x-icon name="credits" /> {{ __('platform.apply_adjustment') }}</button></div>
    </form>
</div>

<section class="admin-panel admin-table-panel">
    <div class="admin-section-heading"><div><p class="eyebrow">{{ __('platform.credits') }}</p><h2>{{ __('platform.ledger') }}</h2></div></div>
    <form class="admin-filter-bar compact" method="GET">
        <label><span>{{ __('platform.type') }}</span><select name="ledger_type"><option value="">{{ __('platform.all') }}</option>@foreach($transactionTypes as $type)<option value="{{ $type }}" @selected(request('ledger_type')===$type)>{{ __('platform.credit_types')[$type] ?? $type }}</option>@endforeach</select></label>
        <label><span>{{ __('platform.feature') }}</span><select name="ledger_feature"><option value="">{{ __('platform.all') }}</option>@foreach($transactionFeatures as $feature)<option value="{{ $feature }}" @selected(request('ledger_feature')===$feature)>{{ $feature }}</option>@endforeach</select></label>
        <label><span>{{ __('platform.from') }}</span><input type="date" name="ledger_from" value="{{ request('ledger_from') }}"></label>
        <label><span>{{ __('platform.to') }}</span><input type="date" name="ledger_to" value="{{ request('ledger_to') }}"></label>
        <div class="filter-actions"><button>{{ __('platform.filter') }}</button><a class="button-link button-secondary" href="{{ route('admin.users.show', $managedUser) }}">{{ __('platform.reset') }}</a></div>
    </form>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th scope="col">{{ __('platform.time') }}</th><th scope="col">{{ __('platform.amount') }}</th><th scope="col">{{ __('platform.type') }}</th><th scope="col">{{ __('platform.feature') }}</th><th scope="col">{{ __('platform.description') }}</th></tr></thead><tbody>
        @forelse($transactions as $tx)<tr><td data-label="{{ __('platform.time') }}">{{ $tx->created_at->format('d/m/Y H:i:s') }}</td><td data-label="{{ __('platform.amount') }}" class="{{ $tx->amount >= 0 ? 'credit-positive' : 'credit-negative' }}">{{ $tx->amount > 0 ? '+' : '' }}{{ $tx->amount }}</td><td data-label="{{ __('platform.type') }}">{{ __('platform.credit_types')[$tx->type] ?? $tx->type }}</td><td data-label="{{ __('platform.feature') }}">{{ $tx->feature_code ?? '—' }}</td><td data-label="{{ __('platform.description') }}">{{ $tx->description }}</td></tr>@empty<tr class="admin-table-empty"><td colspan="5"><x-admin.empty-state /></td></tr>@endforelse
    </tbody></table></div><div class="admin-pagination">{{ $transactions->links() }}</div>
</section>
@endsection
