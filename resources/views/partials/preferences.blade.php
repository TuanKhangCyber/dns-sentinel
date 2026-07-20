<div class="preferences" aria-label="{{ __('ui.theme') }}, {{ __('ui.language') }}">
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

</div>
