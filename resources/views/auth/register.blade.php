<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ui.register') }}</title>
    @include('partials.theme-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page">
    @include('partials.preferences', ['placement' => 'register'])
    <main class="auth-card">
        <h1>{{ __('ui.register') }}</h1>
        <p>{{ __('ui.register_intro') }}</p>

        @if ($errors->any())
            <div class="alert" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="auth-form" @if($recaptcha['enabled'] && $recaptcha['type'] === 'score') data-recaptcha-form data-recaptcha-action="register" data-recaptcha-site-key="{{ $recaptcha['site_key'] }}" @else data-submit-lock @endif>
            @csrf
            <label for="name">{{ __('ui.name') }}</label>
            <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
            @error('name')<p class="field-error">{{ $message }}</p>@enderror

            <label for="email">{{ __('ui.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror

            <label for="password">{{ __('ui.password') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror

            <label for="password_confirmation">{{ __('ui.password_confirmation') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>

            @include('partials.recaptcha', ['recaptcha' => $recaptcha])

            <button type="submit">{{ __('ui.register') }}</button>
        </form>

        <p class="auth-link">{{ __('ui.has_account') }} <a href="{{ route('login') }}">{{ __('ui.login') }}</a></p>
        <p class="auth-link"><a href="{{ route('about') }}">{{ __('platform.about') }}</a> · <a href="{{ route('faq') }}">FAQ</a></p>
    </main>
</body>
</html>
