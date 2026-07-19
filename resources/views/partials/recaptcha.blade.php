@if($recaptcha['enabled'])
    @if($recaptcha['configured'])
        @if($recaptcha['type'] === 'checkbox')
            <div class="recaptcha-widget" aria-label="{{ __('platform.recaptcha_notice') }}">
                <div class="g-recaptcha" data-sitekey="{{ $recaptcha['site_key'] }}" data-theme="dark"></div>
            </div>
            @once
                <script src="https://www.google.com/recaptcha/api.js?hl={{ app()->getLocale() }}" async defer></script>
            @endonce
        @else
            <input type="hidden" name="g-recaptcha-response" value="">
            <p class="form-hint" data-recaptcha-status role="status" aria-live="polite">{{ __('platform.recaptcha_notice') }}</p>
            @once
                <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptcha['site_key']) }}" async defer></script>
            @endonce
        @endif
    @else
        <p class="form-hint" role="alert">{{ __('platform.recaptcha_not_configured') }}</p>
    @endif
@endif
