@extends('admin.layout')
@section('title', __('platform.settings'))
@section('admin-content')
<h1>{{ __('platform.settings') }}</h1>
<form class="platform-card form-grid" method="POST" action="{{ route('admin.settings.update') }}">
    @csrf @method('PUT')
    <h2>{{ __('platform.general') }}</h2>
    <label>{{ __('platform.site_name') }}<input name="site_name" value="{{ $settings['site_name'] ?? config('app.name') }}" required></label>
    <label>{{ __('platform.maintenance_notice') }}<textarea name="maintenance_notice">{{ $settings['maintenance_notice'] ?? '' }}</textarea></label>
    <label>{{ __('platform.default_plan') }}<select name="default_plan">@foreach($plans as $plan)<option value="{{ $plan->code }}" @selected(($settings['default_plan'] ?? 'free')===$plan->code)>{{ $plan->name }}</option>@endforeach</select></label>
    <label>{{ __('platform.signup_credits') }}<input name="default_signup_credits" type="number" min="0" value="{{ $settings['default_signup_credits'] ?? 10 }}"></label>
    <label>{{ __('platform.support_content') }}<textarea name="support_content">{{ $settings['support_content'] ?? '' }}</textarea></label>
    <label class="checkbox-label"><input type="checkbox" name="registration_enabled" value="1" @checked(($settings['registration_enabled'] ?? '1')==='1')> {{ __('platform.registration_enabled') }}</label>
    <h2>Google reCAPTCHA</h2>
    <div class="alert {{ $recaptchaConfigured ? 'success' : '' }}">{{ $recaptchaConfigured ? __('platform.recaptcha_configured') : __('platform.recaptcha_not_configured') }}</div>
    <label class="checkbox-label"><input type="checkbox" name="recaptcha_enabled" value="1" @checked(($settings['recaptcha_enabled'] ?? '0')==='1')> {{ __('platform.enabled') }}</label>
    <label class="checkbox-label"><input type="checkbox" name="recaptcha_login_enabled" value="1" @checked(($settings['recaptcha_login_enabled'] ?? '1')==='1')> {{ __('platform.login_protection') }}</label>
    <label class="checkbox-label"><input type="checkbox" name="recaptcha_register_enabled" value="1" @checked(($settings['recaptcha_register_enabled'] ?? '1')==='1')> {{ __('platform.register_protection') }}</label>
    <label class="checkbox-label"><input type="checkbox" name="recaptcha_password_reset_enabled" value="1" @checked(($settings['recaptcha_password_reset_enabled'] ?? '1')==='1')> {{ __('platform.password_reset_protection') }}</label>
    <label>{{ __('platform.minimum_score') }}<input type="number" step="0.1" min="0.1" max="1" name="recaptcha_min_score" value="{{ $settings['recaptcha_min_score'] ?? '0.5' }}"></label>
    <label>{{ __('platform.login_failure_threshold') }}<input type="number" min="1" max="10" name="recaptcha_login_failure_threshold" value="{{ $settings['recaptcha_login_failure_threshold'] ?? 2 }}"></label>
    <p class="form-hint">{{ __('platform.secret_env_only') }}</p>
    <button>{{ __('platform.save') }}</button>
</form>
@endsection
