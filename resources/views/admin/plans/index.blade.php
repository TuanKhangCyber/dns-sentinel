@extends('admin.layout')
@section('title', __('platform.plans'))
@section('admin-content')
<x-admin.page-header :title="__('platform.plans')" :description="__('platform.plans_intro')" :eyebrow="__('platform.membership_access')" />

<div class="admin-card-grid">
    @foreach($plans as $plan)
        @php($editingThisPlan = (int) old('plan_id') === $plan->id)
        <form class="admin-panel admin-form plan-admin-card" method="POST" action="{{ route('admin.plans.update', $plan) }}" data-confirm="{{ __('platform.confirm_plan_update') }}" data-confirm-title="{{ __('platform.plans') }}" data-submit-lock>
            @csrf @method('PUT')
            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
            <div class="admin-section-heading"><div><p class="eyebrow">{{ strtoupper($plan->code) }}</p><h2>{{ $plan->name }}</h2></div><span class="status-badge {{ $plan->is_active ? 'safe' : 'warning' }}">{{ $plan->is_active ? __('platform.active') : __('platform.disabled') }}</span></div>
            <div class="plan-admin-stats"><span><strong>{{ $plan->users_count }}</strong>{{ __('platform.users') }}</span><span><strong>{{ $plan->features->count() }}</strong>{{ __('platform.features') }}</span></div>
            <label>{{ __('platform.name') }}<input name="name" value="{{ $editingThisPlan ? old('name', $plan->name) : $plan->name }}" required maxlength="100">@if($editingThisPlan && $errors->has('name'))<small class="field-error">{{ $errors->first('name') }}</small>@endif</label>
            <label>{{ __('platform.description') }}<textarea name="description" maxlength="2000">{{ $editingThisPlan ? old('description', $plan->description) : $plan->description }}</textarea>@if($editingThisPlan && $errors->has('description'))<small class="field-error">{{ $errors->first('description') }}</small>@endif</label>
            <div class="form-section-grid">
                <label>{{ __('platform.monthly_credits') }}<input type="number" name="monthly_credit_allowance" value="{{ $editingThisPlan ? old('monthly_credit_allowance', $plan->monthly_credit_allowance) : $plan->monthly_credit_allowance }}" min="0" max="1000000" required>@if($editingThisPlan && $errors->has('monthly_credit_allowance'))<small class="field-error">{{ $errors->first('monthly_credit_allowance') }}</small>@endif</label>
                <label>{{ __('platform.history_days') }}<input type="number" name="history_retention_days" value="{{ $editingThisPlan ? old('history_retention_days', $plan->history_retention_days) : $plan->history_retention_days }}" min="1" max="3650" required>@if($editingThisPlan && $errors->has('history_retention_days'))<small class="field-error">{{ $errors->first('history_retention_days') }}</small>@endif</label>
            </div>
            <input type="hidden" name="is_active" value="0">
            <label class="admin-switch"><input type="checkbox" name="is_active" value="1" @checked($editingThisPlan ? old('is_active') === '1' : $plan->is_active)><span></span>{{ __('platform.active') }}</label>
            @if($editingThisPlan && $errors->has('is_active'))<small class="field-error">{{ $errors->first('is_active') }}</small>@endif
            @if($plan->code === 'free')<p class="form-hint">{{ __('platform.default_plan_protected') }}</p>@endif
            <div class="sticky-form-actions"><button><x-icon name="plans" /> {{ __('platform.save') }}</button></div>
        </form>
    @endforeach
</div>
@endsection
