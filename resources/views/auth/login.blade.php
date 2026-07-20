<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.login') }}</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/dns-logo.jpg') }}">
    @include('partials.theme-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    @include('partials.preferences', ['placement' => 'login'])
    <main class="auth-card">
        <a class="auth-logo" href="{{ route('about') }}" aria-label="{{ config('app.name') }}">
            <span class="logo-frame logo-frame--auth"><img src="{{ asset('images/dns-logo.jpg') }}" alt=""></span>
        </a>
        <header class="auth-card-header">
            <h1>{{ __('ui.login') }}</h1>
            <p class="auth-intro" id="login-intro">{{ __('ui.login_intro') }}</p>
        </header>
        @include('partials.current-datetime')

        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="auth-form" aria-describedby="login-intro" data-submit-lock
              @if($recaptcha['enabled'] && $recaptcha['type'] === 'score')
                  data-recaptcha-form data-recaptcha-action="login" data-recaptcha-site-key="{{ $recaptcha['site_key'] }}"
                  data-recaptcha-error-message="{{ __('platform.recaptcha_widget_error') }}"
              @elseif($recaptcha['enabled'] && $recaptcha['type'] === 'checkbox')
                  data-recaptcha-checkbox-form
                  data-recaptcha-required-message="{{ __('platform.errors.recaptcha_required') }}"
                  data-recaptcha-verified-message="{{ __('platform.recaptcha_verified') }}"
                  data-recaptcha-expired-message="{{ __('platform.recaptcha_widget_expired') }}"
                  data-recaptcha-error-message="{{ __('platform.recaptcha_widget_error') }}"
              @endif>
            @csrf
            <label for="email">{{ __('ui.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror

            <label for="password">{{ __('ui.password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror

            <label class="checkbox-label">
                <input name="remember" type="checkbox" value="1">
                {{ __('ui.remember') }}
            </label>

            @include('partials.recaptcha', ['recaptcha' => $recaptcha])

            <button type="submit"><x-icon name="login" /> {{ __('ui.login') }}</button>
        </form>

        <footer class="auth-footer">
            <p class="auth-link">{{ __('ui.no_account') }} <a href="{{ route('register') }}"><x-icon name="register" /> {{ __('ui.register') }}</a></p>
            <p class="auth-link auth-meta-links"><a href="{{ route('about') }}">{{ __('platform.about') }}</a> <span aria-hidden="true">&middot;</span> <a href="{{ route('faq') }}">FAQ</a></p>
        </footer>
    </main>
</body>
</html>
