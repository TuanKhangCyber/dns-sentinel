@if($recaptcha['enabled'])
    <input type="hidden" name="g-recaptcha-response" value="">
    <p class="form-hint" data-recaptcha-status role="status" aria-live="polite">
        {{ $recaptcha['configured'] ? __('platform.recaptcha_notice') : __('platform.recaptcha_not_configured') }}
    </p>
    @if($recaptcha['configured'])
        @once
            <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($recaptcha['site_key']) }}" async defer></script>
        @endonce
    @endif
@endif
