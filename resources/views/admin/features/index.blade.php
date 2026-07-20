@extends('admin.layout')
@section('title', __('platform.features'))
@section('admin-content')
<x-admin.page-header :title="__('platform.features')" :description="__('platform.features_intro')" :eyebrow="__('platform.feature_flags')" />

<form class="admin-filter-bar" method="GET" action="{{ route('admin.features.index') }}">
    <label class="admin-search-field"><span>{{ __('platform.search') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('platform.feature_search') }}"></label>
    <label><span>{{ __('platform.category') }}</span><select name="category"><option value="">{{ __('platform.all') }}</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></label>
    <label><span>{{ __('platform.status') }}</span><select name="state"><option value="">{{ __('platform.all') }}</option>@foreach(['enabled','disabled','visible','hidden','registered','unregistered'] as $state)<option value="{{ $state }}" @selected(request('state')===$state)>{{ __('platform.'.$state) }}</option>@endforeach</select></label>
    <label><span>{{ __('platform.plan') }}</span><select name="plan"><option value="">{{ __('platform.all') }}</option>@foreach($plans as $plan)<option value="{{ $plan->code }}" @selected(request('plan')===$plan->code)>{{ $plan->name }}</option>@endforeach</select></label>
    <div class="filter-actions"><button>{{ __('platform.filter') }}</button><a class="button-link button-secondary" href="{{ route('admin.features.index') }}">{{ __('platform.reset') }}</a></div>
</form>

<details class="admin-panel admin-create-panel">
    <summary><x-icon name="features" /> {{ __('platform.add_feature_metadata') }}</summary>
    <form class="admin-form" method="POST" action="{{ route('admin.features.store') }}" data-submit-lock>
        @csrf
        <div class="form-section-grid"><label>{{ __('platform.code') }}<input name="code" pattern="[a-z][a-z0-9_]{2,79}" required></label><label>{{ __('platform.name') }}<input name="name" required maxlength="120"></label><label>{{ __('platform.category') }}<input name="category" value="general" required></label><label>{{ __('platform.cost') }}<input type="number" name="credit_cost" value="0" min="0"></label></div>
        <label>{{ __('platform.description') }}<textarea name="description" maxlength="2000"></textarea></label>
        <div class="form-section-grid"><label>{{ __('platform.order') }}<input type="number" name="sort_order" value="0" min="0"></label><label class="admin-switch"><input type="checkbox" name="is_visible" value="1" checked><span></span>{{ __('platform.visible') }}</label></div>
        <p class="form-hint">{{ __('platform.unregistered_feature_hint') }}</p><button>{{ __('platform.save') }}</button>
    </form>
</details>

<div class="admin-card-grid feature-admin-grid">
    @forelse($features as $feature)
        <form class="admin-panel admin-form @unless($registry->has($feature->code)) metadata-only @endunless" method="POST" action="{{ route('admin.features.update', $feature) }}" data-confirm="{{ __('platform.confirm_feature_update') }}" data-confirm-title="{{ __('platform.features') }}" data-submit-lock>
            @csrf @method('PUT')
            <div class="admin-section-heading"><div><p class="eyebrow">{{ $feature->category }}</p><h2>{{ $feature->code }}</h2></div><span class="status-badge {{ $registry->has($feature->code) ? 'safe' : 'warning' }}">{{ $registry->has($feature->code) ? __('platform.registered') : __('platform.not_registered') }}</span></div>
            @if($dependency = $registry->get($feature->code)['dependency'] ?? null)<p class="dependency-label">{{ __('platform.dependency') }}: <strong>{{ $dependency }}</strong></p>@endif
            <label>{{ __('platform.name') }}<input name="name" value="{{ $feature->name }}" required maxlength="120"></label>
            <label>{{ __('platform.description') }}<textarea name="description" maxlength="2000">{{ $feature->description }}</textarea></label>
            <div class="form-section-grid"><label>{{ __('platform.category') }}<input name="category" value="{{ $feature->category }}" required></label><label>{{ __('platform.cost') }}<input type="number" name="credit_cost" value="{{ $feature->credit_cost }}" min="0" required></label><label>{{ __('platform.order') }}<input type="number" name="sort_order" value="{{ $feature->sort_order }}" min="0" required></label></div>
            <div class="switch-row"><label class="admin-switch"><input type="checkbox" name="is_enabled" value="1" @checked($feature->is_enabled) @disabled(!$registry->has($feature->code))><span></span>{{ __('platform.enabled') }}</label><label class="admin-switch"><input type="checkbox" name="is_visible" value="1" @checked($feature->is_visible)><span></span>{{ __('platform.visible') }}</label></div>
            <fieldset class="admin-fieldset"><legend>{{ __('platform.plan_mapping') }}</legend><div class="switch-row">@foreach($plans as $plan)<label class="admin-switch"><input type="checkbox" name="plans[]" value="{{ $plan->id }}" @checked($feature->plans->contains($plan))><span></span>{{ $plan->name }}</label>@endforeach</div></fieldset>
            <div class="sticky-form-actions"><button>{{ __('platform.save') }}</button></div>
        </form>
    @empty<x-admin.empty-state />@endforelse
</div>
<div class="admin-pagination">{{ $features->links() }}</div>
@endsection
