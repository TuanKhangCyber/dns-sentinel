<?php

namespace Tests\Unit;

use App\Exceptions\RecaptchaException;
use App\Models\SystemSetting;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecaptchaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('recaptcha.type', 'score');
        config()->set('recaptcha.clock_skew_seconds', 5);
        config()->set('recaptcha.site_key', 'public-site-key');
        config()->set('recaptcha.secret_key', 'private-secret-key');
        SystemSetting::where('key', 'recaptcha_enabled')->update(['value' => '1']);
        SystemSetting::where('key', 'recaptcha_register_enabled')->update(['value' => '1']);
    }

    public function test_valid_server_verified_token_is_accepted(): void
    {
        Http::fake(['*' => Http::response($this->validPayload(), 200)]);
        app(RecaptchaService::class)->verify($this->requestWithToken('valid-token'), 'register');
        Http::assertSent(fn ($request) => $request['secret'] === 'private-secret-key' && $request['response'] === 'valid-token');
        $this->assertTrue(true);
    }

    public function test_checkbox_token_does_not_require_v3_score_or_action(): void
    {
        config()->set('recaptcha.type', 'checkbox');
        Http::fake(['*' => Http::response([
            'success' => true,
            'hostname' => 'localhost',
            'challenge_ts' => now()->addMilliseconds(500)->toIso8601String(),
        ], 200)]);

        app(RecaptchaService::class)->verify($this->requestWithToken('checkbox-token'), 'register');

        Http::assertSent(fn ($request) => $request['response'] === 'checkbox-token');
    }

    public function test_timestamp_beyond_clock_skew_is_rejected_as_expired(): void
    {
        config()->set('recaptcha.type', 'checkbox');
        Http::fake(['*' => Http::response([
            'success' => true,
            'hostname' => 'localhost',
            'challenge_ts' => now()->addSeconds(6)->toIso8601String(),
        ], 200)]);

        $this->assertRecaptchaError('recaptcha_expired', fn () => app(RecaptchaService::class)
            ->verify($this->requestWithToken('future-token'), 'register'));
    }

    public function test_google_timeout_or_duplicate_is_reported_as_expired(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']], 200)]);

        $this->assertRecaptchaError('recaptcha_expired', fn () => app(RecaptchaService::class)
            ->verify($this->requestWithToken('expired-token'), 'register'));
    }

    public function test_google_secret_error_and_http_failure_are_reported_as_unavailable(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-secret']], 200)]);
        $this->assertRecaptchaError('recaptcha_unavailable', fn () => app(RecaptchaService::class)
            ->verify($this->requestWithToken('configuration-token'), 'register'));

        Http::fake(['*' => Http::response('Unavailable', 503)]);
        $this->assertRecaptchaError('recaptcha_unavailable', fn () => app(RecaptchaService::class)
            ->verify($this->requestWithToken('http-token'), 'register'));
    }

    public function test_rejection_log_does_not_contain_secret_or_token(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-secret'],
        ], 200)]);

        $this->assertRecaptchaError('recaptcha_unavailable', fn () => app(RecaptchaService::class)
            ->verify($this->requestWithToken('sensitive-response-token'), 'register'));

        Log::shouldHaveReceived('notice')->once()->with(
            'reCAPTCHA verification rejected.',
            Mockery::on(function (array $context): bool {
                $encoded = json_encode($context);

                return is_string($encoded)
                    && ! str_contains($encoded, 'private-secret-key')
                    && ! str_contains($encoded, 'sensitive-response-token');
            }),
        );
    }

    public function test_v3_score_outside_google_range_is_rejected(): void
    {
        Http::fake(['*' => Http::response([...$this->validPayload(), 'score' => 1.1], 200)]);

        $this->assertRecaptchaError('recaptcha_failed', fn () => app(RecaptchaService::class)
            ->verify($this->requestWithToken('invalid-score-token'), 'register'));
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_google_response_fails_closed(array|string $payload): void
    {
        Http::fake(['*' => is_string($payload) ? Http::response($payload, 200) : Http::response($payload, 200)]);
        $this->expectException(RecaptchaException::class);
        app(RecaptchaService::class)->verify($this->requestWithToken('invalid-'.md5(serialize($payload))), 'register');
    }

    public static function invalidPayloads(): array
    {
        $now = '2026-07-18T13:00:00Z';

        return [
            'success false' => [['success' => false]],
            'low score' => [['success' => true, 'score' => 0.1, 'action' => 'register', 'challenge_ts' => $now]],
            'wrong action' => [['success' => true, 'score' => 0.9, 'action' => 'login', 'challenge_ts' => $now]],
            'missing fields' => [['success' => true]],
            'invalid json' => ['not-json'],
        ];
    }

    public function test_hostname_mismatch_and_network_failure_fail_closed(): void
    {
        config()->set('recaptcha.expected_hostname', 'expected.test');
        Http::fake(['*' => Http::response([...$this->validPayload(), 'hostname' => 'wrong.test'], 200)]);
        try {
            app(RecaptchaService::class)->verify($this->requestWithToken('host-token'), 'register');
            $this->fail();
        } catch (RecaptchaException) {
            $this->assertTrue(true);
        }
        Http::fake(fn () => throw new \RuntimeException('private-secret-key must not leak'));
        $this->expectException(RecaptchaException::class);
        $this->expectExceptionMessage(__('platform.errors.recaptcha_unavailable'));
        app(RecaptchaService::class)->verify($this->requestWithToken('network-token'), 'register');
    }

    public function test_disabled_recaptcha_does_not_call_google(): void
    {
        SystemSetting::where('key', 'recaptcha_enabled')->update(['value' => '0']);
        Cache::flush();
        Http::fake();
        app(RecaptchaService::class)->verify(new Request, 'register');
        Http::assertNothingSent();
    }

    public function test_token_replay_is_rejected(): void
    {
        Http::fake(['*' => Http::response($this->validPayload(), 200)]);
        $request = $this->requestWithToken('same-token');
        app(RecaptchaService::class)->verify($request, 'register');
        $this->assertRecaptchaError('recaptcha_expired', fn () => app(RecaptchaService::class)->verify($request, 'register'));
    }

    private function validPayload(): array
    {
        return ['success' => true, 'score' => 0.9, 'action' => 'register', 'hostname' => 'localhost', 'challenge_ts' => now()->toIso8601String()];
    }

    private function requestWithToken(string $token): Request
    {
        return Request::create('/register', 'POST', ['g-recaptcha-response' => $token], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    }

    private function assertRecaptchaError(string $errorCode, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected reCAPTCHA error [{$errorCode}].");
        } catch (RecaptchaException $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
        }
    }
}
