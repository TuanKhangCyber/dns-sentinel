<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_is_available(): void
    {
        $this->get('/register')->assertOk()->assertSee('Đăng ký');
    }

    public function test_registration_can_be_disabled_server_side(): void
    {
        SystemSetting::where('key', 'registration_enabled')->update(['value' => '0']);

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Blocked',
            'email' => 'blocked@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.test']);
    }

    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Nguyễn Văn A',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dns');
        $this->assertAuthenticated();
        $user = User::where('email', 'user@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registration_validates_duplicate_email_and_password_confirmation(): void
    {
        User::factory()->create(['email' => 'used@example.com']);

        $this->post('/register', [
            'name' => 'User',
            'email' => 'used@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/dns');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_rejects_an_account_with_an_unknown_non_active_status(): void
    {
        $user = User::factory()->create(['password' => 'password123', 'status' => 'legacy_disabled']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        config()->set('auth.login.max_attempts', 2);
        $user = User::factory()->create(['password' => 'password123']);

        foreach (range(1, 2) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_user_can_change_language_without_storing_country(): void
    {
        $this->from('/login')->post('/preferences', [
            'locale' => 'en',
            'country' => 'JP',
        ])->assertRedirect('/login')
            ->assertSessionHas('locale', 'en')
            ->assertSessionMissing('country');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Log in');
    }

    public function test_preferences_reject_unsupported_values(): void
    {
        $this->post('/preferences', [
            'locale' => 'xx',
        ])->assertSessionHasErrors('locale');
    }
}
