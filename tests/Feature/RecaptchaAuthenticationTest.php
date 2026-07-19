<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('recaptcha.type', 'checkbox');
        config()->set('recaptcha.login_always_visible', true);
        config()->set('recaptcha.site_key', 'site-public');
        config()->set('recaptcha.secret_key', 'secret-private');
        foreach (['recaptcha_enabled', 'recaptcha_register_enabled', 'recaptcha_login_enabled'] as $key) {
            SystemSetting::where('key', $key)->update(['value' => '1']);
        }
    }

    public function test_register_requires_backend_verification_and_creates_membership_only_after_success(): void
    {
        $payload = ['name' => 'Bot Test', 'email' => 'bot@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->post('/register', $payload)->assertSessionHasErrors('recaptcha');
        $this->assertDatabaseMissing('users', ['email' => 'bot@example.test']);
        Http::fake(['*' => Http::response($this->payload(), 200)]);
        $this->post('/register', [...$payload, 'g-recaptcha-response' => 'valid-register'])->assertRedirect('/dns');
        $user = User::where('email', 'bot@example.test')->firstOrFail();
        $this->assertSame('free', $user->plan->code);
        $this->assertNotNull($user->wallet);
        $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'type' => 'grant']);
    }

    public function test_login_requires_visible_checkbox_on_first_attempt_and_accepts_verified_token(): void
    {
        $user = User::factory()->create(['email' => 'login@example.test', 'password' => 'password123']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('class="g-recaptcha"', false)
            ->assertSee('data-sitekey="site-public"', false)
            ->assertSee('google.com/recaptcha/api.js?hl=', false);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertSessionHasErrors('recaptcha');
        Http::fake(['*' => Http::response($this->payload(), 200)]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password123', 'g-recaptcha-response' => 'login-token'])->assertRedirect('/dns');
        $this->assertAuthenticatedAs($user);
    }

    public function test_score_mode_remains_available_for_existing_v3_keys(): void
    {
        config()->set('recaptcha.type', 'score');
        config()->set('recaptcha.login_always_visible', false);
        SystemSetting::where('key', 'recaptcha_login_failure_threshold')->update(['value' => '1']);
        Cache::flush();
        $user = User::factory()->create(['email' => 'adaptive@example.test', 'password' => 'password123']);

        $this->get('/login')->assertOk()->assertDontSee('google.com/recaptcha/api.js', false);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->get('/login')->assertOk()->assertSee('google.com/recaptcha/api.js', false)->assertSee('site-public');

        Http::fake(['*' => Http::response($this->scorePayload('login'), 200)]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password123', 'g-recaptcha-response' => 'adaptive-token'])->assertRedirect('/dns');
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertDontSee('google.com/recaptcha/api.js', false);
    }

    public function test_enabled_registration_with_missing_keys_fails_closed_without_google_request(): void
    {
        config()->set('recaptcha.site_key');
        config()->set('recaptcha.secret_key');
        Http::fake();

        $this->post('/register', [
            'name' => 'Missing Config',
            'email' => 'missing-config@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'g-recaptcha-response' => 'must-not-be-sent',
        ])->assertSessionHasErrors('recaptcha')->assertSessionHas('error_code', 'recaptcha_unavailable');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'missing-config@example.test']);
    }

    public function test_frontend_exposes_site_key_but_never_secret_and_public_about_loads_no_google_script(): void
    {
        $this->get('/register')->assertOk()->assertSee('site-public')->assertDontSee('secret-private');
        $this->get('/about')->assertOk()->assertDontSee('google.com/recaptcha/api.js', false);
    }

    public function test_non_admin_cannot_change_recaptcha_settings(): void
    {
        $this->actingAs(User::factory()->create())->put('/admin/settings', [])->assertForbidden();
    }

    private function payload(): array
    {
        return ['success' => true, 'hostname' => 'localhost', 'challenge_ts' => now()->toIso8601String()];
    }

    private function scorePayload(string $action): array
    {
        return ['success' => true, 'score' => 0.9, 'action' => $action, 'hostname' => 'localhost', 'challenge_ts' => now()->toIso8601String()];
    }
}
