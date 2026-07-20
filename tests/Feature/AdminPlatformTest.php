<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ContentPage;
use App\Models\FaqItem;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_routes_require_admin_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_admin_changes_user_membership_and_creates_filtered_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->free()->create();
        $plus = Plan::where('code', 'plus')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.users.update', $user), ['plan_id' => $plus->id, 'role' => 'user', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('plus', $user->fresh()->plan->code);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'user.updated', 'target_id' => $user->id]);
    }

    public function test_last_active_admin_cannot_be_demoted_or_suspended(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['plan_id' => $admin->plan_id, 'role' => 'user', 'status' => 'active'])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_default_free_plan_cannot_be_disabled_even_without_users(): void
    {
        $admin = User::factory()->admin()->create();
        $free = Plan::where('code', 'free')->firstOrFail();
        User::where('plan_id', $free->id)->update(['plan_id' => Plan::where('code', 'plus')->value('id')]);

        $this->actingAs($admin)->put(route('admin.plans.update', $free), [
            'name' => $free->name,
            'description' => $free->description,
            'monthly_credit_allowance' => $free->monthly_credit_allowance,
            'history_retention_days' => $free->history_retention_days,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($free->fresh()->is_active);
    }

    public function test_database_configured_default_plan_cannot_be_disabled(): void
    {
        $admin = User::factory()->admin()->free()->create();
        $plus = Plan::where('code', 'plus')->firstOrFail();
        SystemSetting::where('key', 'default_plan')->update(['value' => 'plus']);
        Cache::forget('setting:default_plan');

        $this->actingAs($admin)->put(route('admin.plans.update', $plus), [
            'plan_id' => $plus->id,
            'name' => $plus->name,
            'description' => $plus->description,
            'monthly_credit_allowance' => $plus->monthly_credit_allowance,
            'history_retention_days' => $plus->history_retention_days,
            'is_active' => 0,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($plus->fresh()->is_active);
    }

    public function test_admin_adjustment_requires_reason_and_cannot_make_balance_negative(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 2]);
        $this->actingAs($admin)->post(route('admin.users.credits', $user), ['amount' => -3, 'reason' => 'manual correction', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->assertSame(2, $user->wallet->fresh()->balance);
        $this->post(route('admin.users.credits', $user), ['amount' => 5, 'reason' => 'support adjustment', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'credits.adjusted', 'actor_id' => $admin->id]);
    }

    public function test_duplicate_admin_credit_adjustment_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 10]);
        $payload = ['amount' => 5, 'reason' => 'Idempotency regression', 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($admin)->post(route('admin.users.credits', $user), $payload)->assertSessionHasNoErrors();
        $this->post(route('admin.users.credits', $user), $payload)->assertSessionHasNoErrors();

        $this->assertSame(15, $user->wallet->fresh()->balance);
        $this->assertSame(1, $user->creditTransactions()->where('type', 'adjustment')->count());
        $this->assertSame(1, AuditLog::where('action', 'credits.adjusted')->where('target_id', $user->id)->count());
    }

    public function test_reused_credit_adjustment_key_with_different_details_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 10]);
        $key = (string) Str::uuid();

        $this->actingAs($admin)->post(route('admin.users.credits', $user), [
            'amount' => 5, 'reason' => 'First approved adjustment', 'idempotency_key' => $key,
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.users.credits', $user), [
            'amount' => 7, 'reason' => 'Different replay details', 'idempotency_key' => $key,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(15, $user->wallet->fresh()->balance);
        $this->assertSame(1, $user->creditTransactions()->where('type', 'adjustment')->count());
    }

    public function test_credit_adjustment_rolls_back_when_audit_write_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 10]);
        $this->mock(AuditService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit storage unavailable'));
        });

        $this->actingAs($admin)->post(route('admin.users.credits', $user), [
            'amount' => 5,
            'reason' => 'Atomic rollback regression',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertServerError();

        $this->assertSame(10, $user->wallet->fresh()->balance);
        $this->assertSame(0, $user->creditTransactions()->where('type', 'adjustment')->count());
    }

    public function test_suspended_admin_and_regular_user_cannot_access_admin_ledger_pages(): void
    {
        $regularUser = User::factory()->create();
        $suspendedAdmin = User::factory()->admin()->create(['status' => 'suspended']);

        foreach ([route('admin.credits.index'), route('admin.audit.index')] as $url) {
            $this->actingAs($regularUser)->get($url)->assertForbidden();
            $this->actingAs($suspendedAdmin)->get($url)->assertForbidden();
        }
    }

    public function test_unregistered_metadata_feature_cannot_be_enabled(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.features.store'), ['code' => 'future_feature', 'name' => 'Future', 'category' => 'general', 'credit_cost' => 0, 'sort_order' => 0, 'is_visible' => 1])->assertSessionHasNoErrors();
        $feature = Feature::where('code', 'future_feature')->firstOrFail();
        $this->put(route('admin.features.update', $feature), ['name' => 'Future', 'category' => 'general', 'credit_cost' => 0, 'sort_order' => 0, 'is_enabled' => 1])->assertSessionHasErrors('is_enabled');
        $this->assertFalse($feature->fresh()->is_enabled);
    }

    public function test_admin_can_change_registered_feature_plan_mapping(): void
    {
        $admin = User::factory()->admin()->create();
        $free = Plan::where('code', 'free')->firstOrFail();
        $plus = Plan::where('code', 'plus')->firstOrFail();
        $feature = Feature::where('code', 'security_headers')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.features.update', $feature), [
            'name' => $feature->name,
            'description' => $feature->description,
            'category' => $feature->category,
            'credit_cost' => 3,
            'sort_order' => 2,
            'is_enabled' => 1,
            'is_visible' => 1,
            'plans' => [$free->id, $plus->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($feature->fresh()->plans->contains($free));
        $this->assertSame(3, $feature->fresh()->credit_cost);
        $this->assertDatabaseHas('audit_logs', ['action' => 'feature.updated', 'actor_id' => $admin->id]);
    }

    public function test_about_content_is_escaped_and_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $page = ContentPage::where('slug', 'about')->firstOrFail();
        $payload = ['title_vi' => 'Giới thiệu', 'title_en' => 'About', 'content_vi' => '<script>alert(1)</script>', 'content_en' => '<img src=x onerror=alert(1)>', 'is_published' => 1];
        $this->actingAs($admin)->put(route('admin.content.about', $page), $payload)->assertSessionHasNoErrors();
        $this->get('/about')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertTrue(AuditLog::where('action', 'content.about_updated')->exists());
    }

    public function test_admin_can_create_update_and_delete_escaped_faq_content(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = [
            'question_vi' => '<script>Câu hỏi</script>',
            'question_en' => '<script>Question</script>',
            'answer_vi' => '<img src=x onerror=alert(1)>',
            'answer_en' => '<b>Answer</b>',
            'category' => 'security',
            'sort_order' => 10,
            'is_published' => 1,
        ];

        $this->actingAs($admin)->post(route('admin.faqs.store'), $payload)->assertSessionHasNoErrors();
        $faq = FaqItem::where('category', 'security')->firstOrFail();
        $this->get('/faq')->assertOk()->assertSee('&lt;script&gt;Câu hỏi&lt;/script&gt;', false)->assertDontSee('<script>Câu hỏi</script>', false);

        $payload['answer_vi'] = 'Câu trả lời đã sửa';
        $this->put(route('admin.faqs.update', $faq), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Câu trả lời đã sửa', $faq->fresh()->answer['vi']);

        $this->delete(route('admin.faqs.destroy', $faq))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('faq_items', ['id' => $faq->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'faq.deleted', 'actor_id' => $admin->id]);
    }

    public function test_admin_settings_never_return_recaptcha_secret(): void
    {
        config()->set('recaptcha.secret_key', 'do-not-render-this-secret');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertDontSee('do-not-render-this-secret');
    }

    public function test_recaptcha_policy_validation_and_audit_do_not_store_secret(): void
    {
        config()->set('recaptcha.site_key', 'site');
        config()->set('recaptcha.secret_key', 'secret-env-only');
        $admin = User::factory()->admin()->create();
        $settings = app(SystemSettingService::class);
        $settings->get('site_name');
        $payload = ['site_name' => 'DNS', 'default_plan' => 'free', 'default_signup_credits' => 10, 'registration_enabled' => 1,
            'recaptcha_enabled' => 1, 'recaptcha_login_enabled' => 1, 'recaptcha_register_enabled' => 1,
            'recaptcha_password_reset_enabled' => 1, 'recaptcha_type' => 'score', 'recaptcha_login_always_visible' => 1,
            'recaptcha_min_score' => 2, 'recaptcha_login_failure_threshold' => 2];
        $this->actingAs($admin)->put('/admin/settings', $payload)->assertSessionHasErrors('recaptcha_min_score');
        $payload['recaptcha_min_score'] = 0.7;
        $this->put('/admin/settings', $payload)->assertSessionHasNoErrors();
        $audit = AuditLog::where('action', 'settings.updated')->latest()->firstOrFail();
        $this->assertStringNotContainsString('secret-env-only', json_encode([$audit->before, $audit->after]));
        $this->assertSame('DNS', $settings->get('site_name'));
    }

    public function test_failed_settings_transaction_keeps_database_and_cache_consistent(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = app(SystemSettingService::class);
        $originalName = $settings->get('site_name');
        $this->mock(AuditService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        });

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Must Roll Back',
            'default_plan' => 'free',
            'default_signup_credits' => 10,
            'registration_enabled' => 1,
            'recaptcha_enabled' => 0,
            'recaptcha_login_enabled' => 1,
            'recaptcha_register_enabled' => 1,
            'recaptcha_password_reset_enabled' => 1,
            'recaptcha_type' => 'checkbox',
            'recaptcha_login_always_visible' => 1,
            'recaptcha_min_score' => 0.5,
            'recaptcha_login_failure_threshold' => 2,
        ])->assertServerError();

        $this->assertSame($originalName, SystemSetting::where('key', 'site_name')->value('value'));
        $this->assertSame($originalName, $settings->get('site_name'));
    }

    public function test_audit_service_redacts_nested_credentials_and_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $audit = app(AuditService::class)->record($admin, 'security.redaction_test', $admin, [
            'name' => 'safe',
            'password' => 'password-value',
            'nested' => ['g-recaptcha-response-token' => 'token-value', 'api_key' => 'api-value', 'x-api-keys' => 'header-api-value'],
        ], ['secret_key' => 'secret-value', 'private_key' => 'private-value', 'access_key' => 'access-value']);
        $encoded = json_encode([$audit->before, $audit->after], JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('safe', $encoded);
        foreach (['password-value', 'token-value', 'api-value', 'header-api-value', 'secret-value', 'private-value', 'access-value'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $encoded);
        }
    }

    public function test_admin_audit_page_does_not_render_redacted_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'security.legacy_ui_redaction_test',
            'target_type' => User::class,
            'target_id' => $admin->id,
            'before' => [
                'authorization' => 'Bearer private-token',
                'nested' => ['g-recaptcha-response' => 'captcha-token', 'safe' => 'visible-value'],
            ],
            'after' => ['cookie' => 'session-cookie'],
        ]);

        $this->actingAs($admin)->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('visible-value')
            ->assertDontSee('private-token')
            ->assertDontSee('captcha-token')
            ->assertDontSee('session-cookie');
    }

    public function test_make_admin_command_promotes_existing_user_without_changing_password(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $password = $user->password;
        $this->artisan('user:make-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_make_admin_command_rolls_back_when_audit_fails(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->mock(AuditService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        });

        try {
            Artisan::call('user:make-admin', ['email' => $user->email]);
            $this->fail('The command should fail when its audit write fails.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }

        $this->assertSame('user', $user->fresh()->role);
    }
}
