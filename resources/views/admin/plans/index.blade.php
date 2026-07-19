@extends('admin.layout')

@section('admin-content')
    <h1>{{ __('platform.plans') }}</h1>

    <div class="platform-grid">
        @foreach ($plans as $plan)
            <form class="platform-card form-grid" method="POST" action="{{ route('admin.plans.update', $plan) }}">
                @csrf
                @method('PUT')

                <h2>{{ $plan->code }}</h2>

                <label>
                    {{ __('platform.name') }}
                    <input name="name" value="{{ $plan->name }}" required>
                </label>

                <label>
                    {{ __('platform.description') }}
                    <textarea name="description">{{ $plan->description }}</textarea>
                </label>

                <label>
                    {{ __('platform.monthly_credits') }}
                    <input type="number" name="monthly_credit_allowance" value="{{ $plan->monthly_credit_allowance }}" min="0" required>
                </label>

                <label>
                    {{ __('platform.history_days') }}
                    <input type="number" name="history_retention_days" value="{{ $plan->history_retention_days }}" min="1" required>
                </label>

                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" @checked($plan->is_active)>
                    {{ __('platform.active') }}
                </label>

                <p>{{ $plan->users_count }} {{ __('platform.users') }} · {{ $plan->features->count() }} {{ __('platform.features') }}</p>
                <button>{{ __('platform.save') }}</button>
            </form>
        @endforeach
    </div>
@endsection
