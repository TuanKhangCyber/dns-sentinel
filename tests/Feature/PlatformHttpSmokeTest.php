<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ContentPage;
use App\Models\CreditTransaction;
use App\Models\FaqItem;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformHttpSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_admin_credit_content_and_recaptcha_http_workflow(): void
    {
        Bus::fake();
        Http::fake();
        config()->set('scanner.allowlist', ['8.8.8.8', '1.1.1.1']);
        config()->set('scanner.nmap_binary', 'fake-nmap-for-test-only');

        $this->get('/register')->assertOk()->assertDontSee('google.com/recaptcha/api.js', false);
        $this->post('/register', [
            'name' => 'Free Smoke',
            'email' => 'free-smoke@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/dns');
        $freeUser = User::where('email', 'free-smoke@example.test')->firstOrFail();
        $this->assertSame('free', $freeUser->plan->code);
        $this->get('/dns')->assertOk()->assertSee('lookupForm');
        $this->get('/scanner')->assertOk()->assertSee(__('platform.errors.upgrade_required'));

        $scanPayload = ['target' => '8.8.8.8', 'profile' => 'quick_tcp', 'authorization_confirmed' => true];
        $this->postJson('/scanner/scans', $scanPayload)->assertForbidden()->assertJsonPath('error_code', 'upgrade_required');
        $this->assertSame(0, CreditTransaction::where('type', 'usage')->count());

        $admin = User::factory()->create(['email' => 'admin-smoke@example.test', 'role' => 'user']);
        $this->artisan('user:make-admin', ['email' => $admin->email])->assertSuccessful();
        $this->actingAs($freeUser)->get('/admin')->assertForbidden();
        $admin = $admin->fresh();
        $this->actingAs($admin)->get('/admin')->assertOk();

        $plus = Plan::where('code', 'plus')->firstOrFail();
        $this->put(route('admin.users.update', $freeUser), [
            'plan_id' => $plus->id, 'role' => 'user', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertSame('plus', $freeUser->fresh()->plan->code);
        $freeUser = $freeUser->fresh();

        $startingBalance = $freeUser->wallet->balance;
        $this->actingAs($freeUser)->postJson('/scanner/scans', $scanPayload)->assertAccepted();
        $this->postJson('/scanner/scans', $scanPayload)->assertConflict();
        $this->assertSame($startingBalance - 5, $freeUser->wallet->fresh()->balance);
        $this->assertSame(1, CreditTransaction::where('user_id', $freeUser->id)->where('type', 'usage')->count());

        $feature = Feature::where('code', 'nmap_scan')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.features.update', $feature), $this->featurePayload($feature, false, [$plus->id]))->assertSessionHasNoErrors();
        $this->actingAs($freeUser)->get('/scanner')->assertOk()->assertSee(__('platform.errors.feature_disabled'));
        $this->postJson('/scanner/scans', [...$scanPayload, 'target' => '1.1.1.1'])->assertForbidden()->assertJsonPath('error_code', 'feature_disabled');
        $this->assertSame(1, CreditTransaction::where('user_id', $freeUser->id)->where('type', 'usage')->count());
        $this->actingAs($admin)->put(route('admin.features.update', $feature), $this->featurePayload($feature, true, [$plus->id]))->assertSessionHasNoErrors();

        $this->post(route('admin.users.credits', $freeUser), ['amount' => 7, 'reason' => 'Smoke test adjustment'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('credit_transactions', ['user_id' => $freeUser->id, 'type' => 'adjustment', 'created_by' => $admin->id]);
        $this->assertTrue(AuditLog::where('action', 'credits.adjusted')->where('actor_id', $admin->id)->exists());

        $about = ContentPage::where('slug', 'about')->firstOrFail();
        $this->put(route('admin.content.about', $about), [
            'title_vi' => 'Giới thiệu smoke', 'title_en' => 'Smoke About',
            'content_vi' => '<script>unsafe</script>', 'content_en' => '<img src=x onerror=unsafe>', 'is_published' => 1,
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.faqs.store'), [
            'question_vi' => 'Câu hỏi smoke', 'question_en' => 'Smoke question',
            'answer_vi' => '<b>Trả lời</b>', 'answer_en' => '<b>Answer</b>',
            'category' => 'smoke', 'sort_order' => 99, 'is_published' => 1,
        ])->assertSessionHasNoErrors();
        $this->get('/about')->assertOk()->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)->assertDontSee('<script>unsafe</script>', false);
        $this->get('/faq')->assertOk()->assertSee('Câu hỏi smoke')->assertSee('&lt;b&gt;Trả lời&lt;/b&gt;', false);

        $this->put(route('admin.users.update', $freeUser), [
            'plan_id' => $plus->id, 'role' => 'user', 'status' => 'suspended',
        ])->assertSessionHasNoErrors();
        $this->actingAs($freeUser->fresh())->get('/dns')->assertForbidden();

        User::whereKey($freeUser->id)->update(['status' => 'active', 'membership_expires_at' => now()->subMinute()]);
        $this->assertSame('upgrade_required', app(FeatureAccessService::class)->status($freeUser->fresh(), 'nmap_scan')['reason']);
        $this->assertSame('free', $freeUser->fresh()->plan->code);

        auth()->logout();
        SystemSetting::where('key', 'recaptcha_enabled')->update(['value' => '1']);
        SystemSetting::where('key', 'recaptcha_register_enabled')->update(['value' => '1']);
        Cache::flush();
        config()->set('recaptcha.site_key');
        config()->set('recaptcha.secret_key');
        $this->post('/register', [
            'name' => 'Blocked Bot', 'email' => 'blocked-bot@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'g-recaptcha-response' => 'not-sent-to-google',
        ])->assertSessionHasErrors('recaptcha')->assertSessionHas('error_code', 'recaptcha_unavailable');
        Http::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'blocked-bot@example.test']);
        $this->assertTrue(FaqItem::where('category', 'smoke')->exists());
    }

    private function featurePayload(Feature $feature, bool $enabled, array $plans): array
    {
        return [
            'name' => $feature->name, 'description' => $feature->description, 'category' => $feature->category,
            'credit_cost' => $feature->credit_cost, 'sort_order' => $feature->sort_order,
            'is_enabled' => $enabled ? 1 : 0, 'is_visible' => 1, 'plans' => $plans,
        ];
    }
}
