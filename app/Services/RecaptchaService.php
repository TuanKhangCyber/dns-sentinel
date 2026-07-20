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
    private const GOOGLE_ERROR_CODES = [
        'missing-input-secret',
        'invalid-input-secret',
        'missing-input-response',
        'invalid-input-response',
        'bad-request',
        'timeout-or-duplicate',
        'browser-error',
    ];

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
        return trim((string) config('recaptcha.site_key')) !== ''
            && trim((string) config('recaptcha.secret_key')) !== '';
    }

    public function loginRequired(string $throttleKey): bool
    {
        if (! $this->enabledFor('login')) {
            return false;
        }
        if ((bool) $this->settings->get('recaptcha_login_always_visible', config('recaptcha.login_always_visible', true))) {
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
        $token = $request->string('g-recaptcha-response')->trim()->toString();
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

        if (! $response->successful()) {
            $this->reject($action, 'http_error', 'recaptcha_unavailable', ['http_status' => $response->status()]);
        }
        if (! is_array($payload)) {
            $this->reject($action, 'invalid_json', 'recaptcha_unavailable');
        }
        if (($payload['success'] ?? false) !== true) {
            $codes = $this->googleErrorCodes($payload['error-codes'] ?? []);
            $errorCode = match (true) {
                in_array('missing-input-secret', $codes, true), in_array('invalid-input-secret', $codes, true) => 'recaptcha_unavailable',
                in_array('timeout-or-duplicate', $codes, true) => 'recaptcha_expired',
                default => 'recaptcha_failed',
            };
            $this->reject($action, 'google_rejected', $errorCode, ['google_error_codes' => $codes]);
        }
        if ($this->type() === 'score') {
            if (! hash_equals($action, (string) ($payload['action'] ?? ''))) {
                $this->reject($action, 'action_mismatch');
            }
            $minimum = min(1, max(0.1, (float) $this->settings->get('recaptcha_min_score', config('recaptcha.minimum_score', 0.5))));
            $score = $payload['score'] ?? null;
            if (! is_numeric($score) || (float) $score < 0 || (float) $score > 1 || (float) $score < $minimum) {
                $this->reject($action, 'score_rejected');
            }
        }
        $hostname = trim((string) config('recaptcha.expected_hostname', ''));
        if ($hostname !== '' && ! hash_equals(strtolower($hostname), strtolower((string) ($payload['hostname'] ?? '')))) {
            $this->reject($action, 'hostname_mismatch');
        }
        if (! isset($payload['challenge_ts'])) {
            $this->reject($action, 'challenge_timestamp_missing', 'recaptcha_unavailable');
        }
        try {
            $age = Carbon::parse($payload['challenge_ts'])->diffInSeconds(now(), false);
        } catch (Throwable) {
            $this->reject($action, 'challenge_timestamp_invalid', 'recaptcha_unavailable');
        }
        $maximumAge = max(1, (int) config('recaptcha.token_max_age_seconds', 120));
        $clockSkew = min(30, max(0, (int) config('recaptcha.clock_skew_seconds', 5)));
        if ($age < -$clockSkew || $age > $maximumAge) {
            $this->reject($action, 'challenge_expired', 'recaptcha_expired');
        }
        $replayKey = 'recaptcha:used:'.hash('sha256', $action.'|'.$token);
        try {
            $accepted = Cache::add($replayKey, true, now()->addSeconds($maximumAge));
        } catch (Throwable $exception) {
            Log::warning('reCAPTCHA replay protection unavailable.', ['exception' => $exception::class, 'action' => $action]);
            throw new RecaptchaException('recaptcha_unavailable');
        }
        if (! $accepted) {
            $this->reject($action, 'token_replayed', 'recaptcha_expired');
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
        return strtolower(trim((string) $this->settings->get('recaptcha_type', config('recaptcha.type')))) === 'score' ? 'score' : 'checkbox';
    }

    private function googleErrorCodes(mixed $codes): array
    {
        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_intersect(self::GOOGLE_ERROR_CODES, array_map('strval', $codes)));
    }

    private function reject(string $action, string $reason, string $errorCode = 'recaptcha_failed', array $context = []): never
    {
        Log::notice('reCAPTCHA verification rejected.', [
            'action' => $action,
            'type' => $this->type(),
            'reason' => $reason,
            ...$context,
        ]);

        throw new RecaptchaException($errorCode);
    }
}
