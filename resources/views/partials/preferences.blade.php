@php
    $countries = ['VN' => '🇻🇳', 'US' => '🇺🇸', 'GB' => '🇬🇧', 'JP' => '🇯🇵', 'KR' => '🇰🇷', 'SG' => '🇸🇬'];
    $selectedCountry = session('country', 'VN');
@endphp

<div class="preferences" aria-label="{{ __('ui.theme') }}, {{ __('ui.language') }}, {{ __('ui.country') }}">
    <button type="button" class="theme-toggle button-secondary" aria-label="{{ __('ui.theme') }}">
        <span class="theme-icon" aria-hidden="true">🌙</span>
        <span class="theme-label">{{ __('ui.dark_mode') }}</span>
    </button>

    <form method="POST" action="{{ route('preferences.update') }}" class="preference-form">
        @csrf
        <label class="sr-only" for="locale-{{ $placement }}">{{ __('ui.language') }}</label>
        <select id="locale-{{ $placement }}" name="locale" class="preference-select" data-auto-submit>
            <option value="vi" @selected(app()->getLocale() === 'vi')>🇻🇳 Tiếng Việt</option>
            <option value="en" @selected(app()->getLocale() === 'en')>🇬🇧 English</option>
        </select>
    </form>

    <form method="POST" action="{{ route('preferences.update') }}" class="preference-form country-form">
        @csrf
        <span class="country-flag" aria-hidden="true">{{ $countries[$selectedCountry] }}</span>
        <label class="sr-only" for="country-{{ $placement }}">{{ __('ui.country') }}</label>
        <select id="country-{{ $placement }}" name="country" class="preference-select" data-auto-submit>
            @foreach ($countries as $code => $flag)
                <option value="{{ $code }}" @selected($selectedCountry === $code)>{{ $flag }} {{ __('ui.countries.'.$code) }}</option>
            @endforeach
        </select>
    </form>
</div>
