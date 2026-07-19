<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ContentPage;
use App\Models\FaqItem;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_adjustment_requires_reason_and_cannot_make_balance_negative(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->wallet()->create(['balance' => 2]);
        $this->actingAs($admin)->post(route('admin.users.credits', $user), ['amount' => -3, 'reason' => 'manual correction'])->assertSessionHasErrors('amount');
        $this->assertSame(2, $user->wallet->fresh()->balance);
        $this->post(route('admin.users.credits', $user), ['amount' => 5, 'reason' => 'support adjustment'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'credits.adjusted', 'actor_id' => $admin->id]);
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
        $payload = ['site_name' => 'DNS', 'default_plan' => 'free', 'default_signup_credits' => 10, 'registration_enabled' => 1,
            'recaptcha_enabled' => 1, 'recaptcha_login_enabled' => 1, 'recaptcha_register_enabled' => 1,
            'recaptcha_password_reset_enabled' => 1, 'recaptcha_min_score' => 2, 'recaptcha_login_failure_threshold' => 2];
        $this->actingAs($admin)->put('/admin/settings', $payload)->assertSessionHasErrors('recaptcha_min_score');
        $payload['recaptcha_min_score'] = 0.7;
        $this->put('/admin/settings', $payload)->assertSessionHasNoErrors();
        $audit = AuditLog::where('action', 'settings.updated')->latest()->firstOrFail();
        $this->assertStringNotContainsString('secret-env-only', json_encode([$audit->before, $audit->after]));
    }

    public function test_audit_service_redacts_nested_credentials_and_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $audit = app(AuditService::class)->record($admin, 'security.redaction_test', $admin, [
            'name' => 'safe',
            'password' => 'password-value',
            'nested' => ['g-recaptcha-response-token' => 'token-value', 'api_key' => 'api-value'],
        ], ['secret_key' => 'secret-value']);
        $encoded = json_encode([$audit->before, $audit->after], JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('safe', $encoded);
        foreach (['password-value', 'token-value', 'api-value', 'secret-value'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $encoded);
        }
    }

    public function test_make_admin_command_promotes_existing_user_without_changing_password(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $password = $user->password;
        $this->artisan('user:make-admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertSame($password, $user->fresh()->password);
    }
}
