@extends('layouts.app')
@section('title', __('platform.credits'))
@section('content')
<section class="platform-hero">
    <p class="eyebrow">CREDIT WALLET</p><h1>{{ __('platform.credits') }}</h1><p>{{ __('platform.credit_policy_short') }}</p>
</section>
<div class="metric-grid wallet-metrics">
    <article class="metric-card"><strong>{{ $user->wallet?->balance ?? 0 }}</strong><span>{{ __('platform.balance') }}</span></article>
    <article class="metric-card"><strong>{{ $plan->monthly_credit_allowance }}</strong><span>{{ __('platform.monthly_credits') }}</span></article>
    <article class="metric-card"><strong>{{ $plan->name }}</strong><span>{{ __('platform.current_plan') }}</span></article>
</div>
<section class="platform-card">
    <header class="card-heading"><div><p class="eyebrow">LEDGER</p><h2>{{ __('platform.credits') }}</h2></div></header>
    <div class="table-wrap"><table><thead><tr><th>{{ __('platform.time') }}</th><th>{{ __('platform.amount') }}</th><th>{{ __('platform.type') }}</th><th>{{ __('platform.feature') }}</th><th>{{ __('platform.description') }}</th></tr></thead><tbody>@forelse($transactions as $transaction)<tr><td><time datetime="{{ $transaction->created_at->toIso8601String() }}">{{ $transaction->created_at->translatedFormat('d/m/Y H:i') }}</time></td><td class="{{ $transaction->amount >= 0 ? 'credit-positive' : 'credit-negative' }}"><strong>{{ $transaction->amount > 0 ? '+' : '' }}{{ $transaction->amount }}</strong></td><td><span class="badge unknown">{{ $transaction->type }}</span></td><td>{{ $transaction->feature_code ? str($transaction->feature_code)->replace('_', ' ')->title() : '—' }}</td><td>{{ $transaction->description }}</td></tr>@empty<tr><td colspan="5" class="empty-state">{{ __('platform.no_items') }}</td></tr>@endforelse</tbody></table></div>
    {{ $transactions->links() }}
</section>
@endsection
