@extends('admin.layout')
@section('title', __('platform.credit_ledger'))
@section('admin-content')
<x-admin.page-header :title="__('platform.credit_ledger')" :description="__('platform.credit_ledger_intro')" :eyebrow="__('platform.credits')" />

<form class="admin-filter-bar" method="GET" action="{{ route('admin.credits.index') }}">
    <label class="admin-search-field"><span>{{ __('platform.user') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('platform.email_or_name') }}"></label>
    <label><span>{{ __('platform.type') }}</span><select name="type"><option value="">{{ __('platform.all') }}</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ __('platform.credit_types')[$type] ?? $type }}</option>@endforeach</select></label>
    <label><span>{{ __('platform.feature') }}</span><select name="feature"><option value="">{{ __('platform.all') }}</option>@foreach($features as $feature)<option value="{{ $feature }}" @selected(request('feature')===$feature)>{{ $feature }}</option>@endforeach</select></label>
    <label><span>{{ __('platform.from') }}</span><input type="date" name="from" value="{{ request('from') }}"></label><label><span>{{ __('platform.to') }}</span><input type="date" name="to" value="{{ request('to') }}"></label>
    <div class="filter-actions"><button>{{ __('platform.filter') }}</button><a class="button-link button-secondary" href="{{ route('admin.credits.index') }}">{{ __('platform.reset') }}</a></div>
</form>

<section class="admin-panel admin-table-panel"><div class="table-wrap"><table class="admin-table"><thead><tr><th scope="col">{{ __('platform.time') }}</th><th scope="col">{{ __('platform.user') }}</th><th scope="col">{{ __('platform.amount') }}</th><th scope="col">{{ __('platform.type') }}</th><th scope="col">{{ __('platform.feature') }}</th><th scope="col">{{ __('platform.actor') }}</th><th scope="col">{{ __('platform.description') }}</th></tr></thead><tbody>
@forelse($transactions as $transaction)<tr><td data-label="{{ __('platform.time') }}">{{ $transaction->created_at->format('d/m/Y H:i:s') }}</td><td data-label="{{ __('platform.user') }}">@if($transaction->user)<a href="{{ route('admin.users.show', $transaction->user) }}">{{ $transaction->user->name }}</a><small>{{ $transaction->user->email }}</small>@else—@endif</td><td data-label="{{ __('platform.amount') }}" class="{{ $transaction->amount >= 0 ? 'credit-positive' : 'credit-negative' }}">{{ $transaction->amount > 0 ? '+' : '' }}{{ $transaction->amount }}</td><td data-label="{{ __('platform.type') }}">{{ __('platform.credit_types')[$transaction->type] ?? $transaction->type }}</td><td data-label="{{ __('platform.feature') }}">{{ $transaction->feature_code ?? '—' }}</td><td data-label="{{ __('platform.actor') }}">{{ $transaction->actor?->name ?? __('platform.system') }}</td><td data-label="{{ __('platform.description') }}">{{ $transaction->description }}</td></tr>@empty<tr class="admin-table-empty"><td colspan="7"><x-admin.empty-state /></td></tr>@endforelse
</tbody></table></div><div class="admin-pagination">{{ $transactions->links() }}</div></section>
@endsection
