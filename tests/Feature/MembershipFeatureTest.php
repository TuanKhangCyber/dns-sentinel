<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Scan;
use App\Models\User;
use App\Services\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MembershipFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_and_plus_access_comes_from_plan_mapping(): void
    {
        $access = app(FeatureAccessService::class);
        $free = User::factory()->free()->create();
        $plus = User::factory()->create();

        $this->assertTrue($access->canUse($free, 'dns_lookup'));
        $this->assertSame('upgrade_required', $access->status($free, 'security_headers')['reason']);
        $this->assertTrue($access->canUse($plus, 'security_headers'));
    }

    public function test_global_off_blocks_every_user_including_admin(): void
    {
        Feature::where('code', 'security_headers')->update(['is_enabled' => false]);
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
            $this->assertSame('feature_disabled', app(FeatureAccessService::class)->status($user, 'security_headers')['reason']);
        }
    }

    public function test_expired_plus_membership_falls_back_to_free_policy(): void
    {
        $user = User::factory()->create(['membership_expires_at' => now()->subMinute()]);
        $status = app(FeatureAccessService::class)->status($user, 'nmap_scan');
        $this->assertSame('upgrade_required', $status['reason']);
        $this->assertSame('free', $user->fresh()->plan->code);
    }

    public function test_suspended_user_cannot_use_features_or_authenticated_routes(): void
    {
        $user = User::factory()->suspended()->create();
        $this->assertSame('account_suspended', app(FeatureAccessService::class)->status($user, 'dns_lookup')['reason']);
        $this->actingAs($user)->get('/dns')->assertForbidden();
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    }

    public function test_unknown_account_status_is_denied_fail_closed(): void
    {
        $user = User::factory()->create(['status' => 'legacy_disabled']);

        $this->assertSame('account_suspended', app(FeatureAccessService::class)->status($user, 'dns_lookup')['reason']);
        $this->actingAs($user)->get('/dns')->assertForbidden();
    }

    public function test_hidden_feature_is_absent_and_visible_disabled_feature_is_locked(): void
    {
        $user = User::factory()->create();
        Feature::where('code', 'subdomain_scan')->update(['is_visible' => false]);
        Feature::where('code', 'security_headers')->update(['is_enabled' => false, 'is_visible' => true]);

        $this->actingAs($user)->get('/membership')->assertOk()
            ->assertDontSee('subdomain_scan')->assertSee('security_headers')->assertSee(__('platform.errors.feature_disabled'));
    }

    public function test_admin_can_change_plan_feature_mapping_without_code_change(): void
    {
        $free = Plan::where('code', 'free')->firstOrFail();
        $feature = Feature::where('code', 'security_headers')->firstOrFail();
        $feature->plans()->syncWithoutDetaching([$free->id => ['is_enabled' => true]]);
        $this->assertTrue(app(FeatureAccessService::class)->canUse(User::factory()->free()->create(), 'security_headers'));
    }

    public function test_disabled_feature_cannot_be_called_directly_and_does_not_charge(): void
    {
        config()->set('scanner.allowlist', ['8.8.8.8']);
        Bus::fake();
        $user = User::factory()->create();
        Feature::where('code', 'nmap_scan')->update(['is_enabled' => false]);
        $this->actingAs($user)->postJson('/scanner/scans', ['target' => '8.8.8.8', 'profile' => 'quick_tcp', 'authorization_confirmed' => true])
            ->assertForbidden()->assertJsonPath('error_code', 'feature_disabled');
        $this->assertSame(0, CreditTransaction::where('type', 'usage')->count());
        Bus::assertNothingDispatched();
    }

    public function test_scan_history_direct_endpoints_are_gated_after_downgrade(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'target' => '8.8.8.8',
            'target_type' => 'ipv4',
            'profile' => 'quick_tcp',
            'scanner_type' => 'nmap',
            'status' => 'completed',
            'stage' => 'completed',
            'progress' => 100,
            'authorization_confirmed_at' => now(),
        ]);
        $user->update(['plan_id' => Plan::where('code', 'free')->value('id')]);

        $this->actingAs($user)->get(route('scanner.show', $scan))->assertForbidden();
        $this->getJson(route('scanner.scans.status', $scan))->assertForbidden()->assertJsonPath('error_code', 'upgrade_required');
        $this->getJson(route('scanner.scans.results', $scan))->assertForbidden()->assertJsonPath('error_code', 'upgrade_required');
    }

    public function test_suspension_takes_precedence_over_feature_metadata(): void
    {
        $user = User::factory()->suspended()->create();

        $this->assertSame('account_suspended', app(FeatureAccessService::class)->status($user, 'unknown_feature')['reason']);
    }
}
