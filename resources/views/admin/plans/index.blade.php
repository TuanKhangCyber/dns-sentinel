@extends('admin.layout')

@section('admin-content')
    <h1>Plans</h1>

    <div class="platform-grid">
        @foreach ($plans as $plan)
            <form class="platform-card form-grid" method="POST" action="{{ route('admin.plans.update', $plan) }}">
                @csrf
                @method('PUT')

                <h2>{{ $plan->code }}</h2>

                <label>
                    Name
                    <input name="name" value="{{ $plan->name }}" required>
                </label>

                <label>
                    Description
                    <textarea name="description">{{ $plan->description }}</textarea>
                </label>

                <label>
                    Monthly credits
                    <input type="number" name="monthly_credit_allowance" value="{{ $plan->monthly_credit_allowance }}" min="0" required>
                </label>

                <label>
                    History days
                    <input type="number" name="history_retention_days" value="{{ $plan->history_retention_days }}" min="1" required>
                </label>

                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" @checked($plan->is_active)>
                    Active
                </label>

                <p>{{ $plan->users_count }} users · {{ $plan->features->count() }} features</p>
                <button>{{ __('platform.save') }}</button>
            </form>
        @endforeach
    </div>
@endsection
