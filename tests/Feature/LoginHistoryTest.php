<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_recorded_and_logout_closes_the_session(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->withHeader('User-Agent', 'Test Browser 1.0')->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/dns');

        $history = LoginHistory::whereBelongsTo($user)->sole();
        $this->assertSame('127.0.0.1', $history->ip_address);
        $this->assertSame('Test Browser 1.0', $history->user_agent);
        $this->assertNull($history->logout_at);
        $this->assertNotEmpty($history->getRawOriginal('session_hash'));
        $this->assertArrayNotHasKey('session_hash', $history->toArray());

        $this->post('/logout')->assertRedirect('/login');

        $this->assertNotNull($history->fresh()->logout_at);
    }

    public function test_failed_login_does_not_create_history(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('login_histories', 0);
    }

    public function test_registration_creates_login_history(): void
    {
        $this->post('/register', [
            'name' => 'New User',
            'email' => 'new-user@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/dns');

        $user = User::where('email', 'new-user@example.test')->sole();
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id]);
    }

    public function test_user_can_only_view_their_own_login_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->loginHistories()->create($this->historyData('192.0.2.10', 'Own Browser'));
        $other->loginHistories()->create($this->historyData('192.0.2.20', 'Other Browser'));

        $this->actingAs($user)->get('/login-history')
            ->assertOk()
            ->assertSee('Own Browser')
            ->assertDontSee('Other Browser')
            ->assertDontSee('192.0.2.20');

    }

    public function test_guest_cannot_view_login_history(): void
    {
        $this->get('/login-history')->assertRedirect('/login');
    }

    private function historyData(string $ip, string $agent): array
    {
        return [
            'session_hash' => hash('sha256', $ip.$agent),
            'ip_address' => $ip,
            'user_agent' => $agent,
            'login_at' => now(),
        ];
    }
}
