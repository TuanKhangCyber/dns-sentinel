@extends('admin.layout')
@section('title', $managedUser->name)
@section('admin-content')
<header class="platform-hero"><p class="eyebrow">{{ strtoupper(__('platform.user_management')) }}</p><h1>{{ $managedUser->name }}</h1><p>{{ $managedUser->email }}</p></header>
<div class="platform-grid">
    <form class="platform-card form-grid" method="POST" action="{{ route('admin.users.update', $managedUser) }}" data-submit-lock>
        @csrf @method('PUT')<h2>{{ __('platform.membership') }}</h2>
        <label>{{ __('platform.plan') }}<select name="plan_id">@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($managedUser->plan_id===$plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
        <label>{{ __('platform.role') }}<select name="role"><option @selected($managedUser->role==='user')>user</option><option @selected($managedUser->role==='admin')>admin</option></select></label>
        <label>{{ __('platform.status') }}<select name="status"><option @selected($managedUser->status==='active')>active</option><option @selected($managedUser->status==='suspended')>suspended</option></select></label>
        <label>{{ __('platform.expiry') }}<input type="datetime-local" name="membership_expires_at" value="{{ $managedUser->membership_expires_at?->format('Y-m-d\TH:i') }}"></label>
        <button>{{ __('platform.save') }}</button>
    </form>
    <form class="platform-card form-grid" method="POST" action="{{ route('admin.users.credits', $managedUser) }}" data-confirm="{{ __('platform.confirm_credit_adjustment') }}">
        @csrf<h2>{{ __('platform.adjust_credits') }}</h2><p>{{ __('platform.balance') }}: <strong>{{ $managedUser->wallet?->balance ?? 0 }}</strong></p>
        <label>{{ __('platform.amount') }}<input name="amount" type="number" required></label>
        <label>{{ __('platform.reason') }}<textarea name="reason" required minlength="5"></textarea></label>
        <button>{{ __('platform.save') }}</button>
    </form>
</div>
<section class="platform-card"><h2>{{ __('platform.ledger') }}</h2><div class="table-wrap"><table><tbody>@forelse($transactions as $tx)<tr><td>{{ $tx->created_at }}</td><td class="{{ $tx->amount >= 0 ? 'credit-positive' : 'credit-negative' }}">{{ $tx->amount }}</td><td>{{ $tx->type }}</td><td>{{ $tx->description }}</td></tr>@empty<tr><td class="empty-state">{{ __('platform.no_items') }}</td></tr>@endforelse</tbody></table></div>{{ $transactions->links() }}</section>
@endsection
