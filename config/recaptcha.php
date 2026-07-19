<?php

return [
    'enabled' => filter_var(env('RECAPTCHA_ENABLED', false), FILTER_VALIDATE_BOOL),
    'type' => env('RECAPTCHA_TYPE', 'checkbox'),
    'site_key' => env('RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    'minimum_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
    'expected_hostname' => env('RECAPTCHA_EXPECTED_HOSTNAME'),
    'login_enabled' => filter_var(env('RECAPTCHA_LOGIN_ENABLED', true), FILTER_VALIDATE_BOOL),
    'login_always_visible' => filter_var(env('RECAPTCHA_LOGIN_ALWAYS_VISIBLE', true), FILTER_VALIDATE_BOOL),
    'register_enabled' => filter_var(env('RECAPTCHA_REGISTER_ENABLED', true), FILTER_VALIDATE_BOOL),
    'password_reset_enabled' => filter_var(env('RECAPTCHA_PASSWORD_RESET_ENABLED', true), FILTER_VALIDATE_BOOL),
    'login_failure_threshold' => (int) env('RECAPTCHA_LOGIN_FAILURE_THRESHOLD', 2),
    'connect_timeout' => (int) env('RECAPTCHA_CONNECT_TIMEOUT_SECONDS', 3),
    'timeout' => (int) env('RECAPTCHA_TIMEOUT_SECONDS', 5),
    'token_max_age_seconds' => (int) env('RECAPTCHA_TOKEN_MAX_AGE_SECONDS', 120),
    'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
];
