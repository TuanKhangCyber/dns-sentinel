<?php

namespace App\Services;

use App\Exceptions\RecaptchaException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class RecaptchaService
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function enabledFor(string $action): bool
    {
        if (! $this->settings->get('recaptcha_enabled', config('recaptcha.enabled', false))) {
            return false;
        }

        return (bool) $this->settings->get('recaptcha_'.$action.'_enabled', config('recaptcha.'.$action.'_enabled', false));
    }

    public function configured(): bool
    {
        return filled(config('recaptcha.site_key')) && filled(config('recaptcha.secret_key'));
    }

    public function loginRequired(string $throttleKey): bool
    {
        if (! $this->enabledFor('login')) {
            return false;
        }
        if ((bool) config('recaptcha.login_always_visible', true)) {
            return true;
        }

        $threshold = max(1, (int) $this->settings->get('recaptcha_login_failure_threshold', config('recaptcha.login_failure_threshold', 2)));

        return RateLimiter::attempts($throttleKey) >= $threshold;
    }

    public function verify(Request $request, string $action): void
    {
        if (! $this->enabledFor($action)) {
            return;
        }
        if (! $this->configured()) {
            throw new RecaptchaException('recaptcha_unavailable');
        }
        $token = $request->string('g-recaptcha-response')->toString();
        if ($token === '') {
            throw new RecaptchaException('recaptcha_required');
        }

        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout((int) config('recaptcha.connect_timeout', 3))
                ->timeout((int) config('recaptcha.timeout', 5))
                ->withOptions(['allow_redirects' => false])
                ->post(config('recaptcha.verify_url'), [
                    'secret' => config('recaptcha.secret_key'), 'response' => $token, 'remoteip' => $request->ip(),
                ]);
            $payload = $response->successful() ? $response->json() : null;
        } catch (Throwable $exception) {
            Log::warning('reCAPTCHA verification unavailable.', ['exception' => $exception::class, 'action' => $action]);
            throw new RecaptchaException('recaptcha_unavailable');
        }

        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            throw new RecaptchaException;
        }
        if ($this->type() === 'score') {
            if (! hash_equals($action, (string) ($payload['action'] ?? ''))) {
                throw new RecaptchaException;
            }
            $minimum = (float) $this->settings->get('recaptcha_min_score', config('recaptcha.minimum_score', 0.5));
            if (! isset($payload['score']) || ! is_numeric($payload['score']) || (float) $payload['score'] < $minimum) {
                throw new RecaptchaException;
            }
        }
        $hostname = trim((string) config('recaptcha.expected_hostname', ''));
        if ($hostname !== '' && ! hash_equals(strtolower($hostname), strtolower((string) ($payload['hostname'] ?? '')))) {
            throw new RecaptchaException;
        }
        if (! isset($payload['challenge_ts'])) {
            throw new RecaptchaException;
        }
        try {
            $age = Carbon::parse($payload['challenge_ts'])->diffInSeconds(now(), false);
        } catch (Throwable) {
            throw new RecaptchaException;
        }
        if ($age < 0 || $age > (int) config('recaptcha.token_max_age_seconds', 120)) {
            throw new RecaptchaException;
        }
        $replayKey = 'recaptcha:used:'.hash('sha256', $action.'|'.$token);
        if (! Cache::add($replayKey, true, now()->addSeconds((int) config('recaptcha.token_max_age_seconds', 120)))) {
            throw new RecaptchaException;
        }
    }

    public function publicConfig(string $action, ?bool $enabled = null): array
    {
        $enabled ??= $this->enabledFor($action);

        return [
            'enabled' => $enabled, 'configured' => $this->configured(),
            'site_key' => $enabled ? config('recaptcha.site_key') : null, 'action' => $action,
            'type' => $this->type(),
        ];
    }

    private function type(): string
    {
        return config('recaptcha.type') === 'score' ? 'score' : 'checkbox';
    }
}
