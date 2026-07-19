@extends('admin.layout')
@section('title', __('platform.features'))
@section('admin-content')
<h1>{{ __('platform.features') }}</h1>
<details class="platform-card">
    <summary>{{ __('platform.add_feature_metadata') }}</summary>
    <form class="form-grid" method="POST" action="{{ route('admin.features.store') }}">
        @csrf
        <label>{{ __('platform.code') }}<input name="code" pattern="[a-z][a-z0-9_]{2,79}" required></label>
        <label>{{ __('platform.name') }}<input name="name" required></label>
        <label>{{ __('platform.category') }}<input name="category" value="general" required></label>
        <label>{{ __('platform.description') }}<textarea name="description"></textarea></label>
        <label>{{ __('platform.cost') }}<input type="number" name="credit_cost" value="0" min="0"></label>
        <label>{{ __('platform.order') }}<input type="number" name="sort_order" value="0" min="0"></label>
        <label class="checkbox-label"><input type="checkbox" name="is_visible" value="1" checked> {{ __('platform.visible') }}</label>
        <button>{{ __('platform.save') }}</button>
    </form>
</details>
<div class="platform-grid">
    @foreach($features as $feature)
        <form class="platform-card form-grid @unless($registry->has($feature->code)) locked @endunless" method="POST" action="{{ route('admin.features.update', $feature) }}">
            @csrf @method('PUT')
            <h2>{{ $feature->code }}</h2>
            <span class="badge {{ $registry->has($feature->code) ? 'safe' : 'warning' }}">{{ $registry->has($feature->code) ? __('platform.registered') : __('platform.not_registered') }}</span>
            <label>{{ __('platform.name') }}<input name="name" value="{{ $feature->name }}" required></label>
            <label>{{ __('platform.description') }}<textarea name="description">{{ $feature->description }}</textarea></label>
            <label>{{ __('platform.category') }}<input name="category" value="{{ $feature->category }}" required></label>
            <label>{{ __('platform.cost') }}<input type="number" name="credit_cost" value="{{ $feature->credit_cost }}" min="0" required></label>
            <label>{{ __('platform.order') }}<input type="number" name="sort_order" value="{{ $feature->sort_order }}" min="0" required></label>
            <label class="checkbox-label"><input type="checkbox" name="is_enabled" value="1" @checked($feature->is_enabled)> {{ __('platform.enabled') }}</label>
            <label class="checkbox-label"><input type="checkbox" name="is_visible" value="1" @checked($feature->is_visible)> {{ __('platform.visible') }}</label>
            <fieldset><legend>{{ __('platform.plans') }}</legend>@foreach($plans as $plan)<label class="checkbox-label"><input type="checkbox" name="plans[]" value="{{ $plan->id }}" @checked($feature->plans->contains($plan))>{{ $plan->name }}</label>@endforeach</fieldset>
            <button>{{ __('platform.save') }}</button>
        </form>
    @endforeach
</div>
@endsection
