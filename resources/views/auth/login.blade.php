<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.login') }}</title>
    @include('partials.theme-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    @include('partials.preferences', ['placement' => 'login'])
    <main class="auth-card">
        <h1>{{ __('ui.login') }}</h1>
        <p>{{ __('ui.login_intro') }}</p>

        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="auth-form" @if($recaptcha['enabled']) data-recaptcha-form data-recaptcha-action="login" data-recaptcha-site-key="{{ $recaptcha['site_key'] }}" @else data-submit-lock @endif>
            @csrf
            <label for="email">{{ __('ui.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror

            <label for="password">{{ __('ui.password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror

            <label class="checkbox-label">
                <input name="remember" type="checkbox" value="1">
                {{ __('ui.remember') }}
            </label>

            @include('partials.recaptcha', ['recaptcha' => $recaptcha])

            <button type="submit">{{ __('ui.login') }}</button>
        </form>

        <p class="auth-link">{{ __('ui.no_account') }} <a href="{{ route('register') }}">{{ __('ui.register') }}</a></p>
        <p class="auth-link"><a href="{{ route('about') }}">{{ __('platform.about') }}</a> · <a href="{{ route('faq') }}">FAQ</a></p>
    </main>
</body>
</html>
