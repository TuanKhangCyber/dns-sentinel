<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_dns_remains_available_without_executable_export_controls(): void
    {
        $user = User::factory()->free()->create();

        $this->actingAs($user)->get('/dns')->assertOk()
            ->assertSee('id="lookupForm"', false)
            ->assertDontSee('id="exportJson"', false)
            ->assertDontSee('id="exportPdf"', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_plus_export_controls_start_disabled_and_receive_urls_only_after_a_result(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dns')->assertOk()
            ->assertSee('data-recon-export', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('id="exportJson" class="button-link" href=', false)
            ->assertDontSee('id="exportPdf" class="button-link" href=', false);

        $this->assertUniqueIds($response->getContent());
    }

    public function test_mixed_export_permissions_do_not_render_missing_control_contracts(): void
    {
        Feature::where('code', 'export_pdf')->update(['is_enabled' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dns')->assertOk()
            ->assertSee('id="exportJson"', false)
            ->assertDontSee('id="exportPdf"', false)
            ->assertDontSee("getElementById('exportPdf').href", false);
    }

    public function test_hidden_scanner_features_are_not_rendered_and_visible_disabled_state_has_no_link(): void
    {
        Feature::whereIn('code', ['nmap_scan', 'vulnerability_scan'])->update(['is_visible' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dns')->assertOk()
            ->assertDontSee('data-tab-target="nmap"', false)
            ->assertDontSee(route('scanner.index'), false);

        Feature::where('code', 'nmap_scan')->update(['is_visible' => true, 'is_enabled' => false]);
        $response = $this->get('/dns')->assertOk()
            ->assertSee('navigation-locked', false)
            ->assertSee(__('platform.errors.feature_disabled'));
        $this->assertUniqueIds($response->getContent());
    }

    public function test_admin_navigation_is_only_rendered_for_admins(): void
    {
        $user = User::factory()->free()->create();
        $this->actingAs($user)->get('/dns')->assertOk()->assertDontSee(route('admin.dashboard'), false);

        $admin = User::factory()->admin()->create(['plan_id' => Plan::where('code', 'plus')->value('id')]);
        $this->actingAs($admin)->get('/dns')->assertOk()->assertSee(route('admin.dashboard'), false);
    }

    public function test_primary_pages_render_without_duplicate_ids(): void
    {
        $user = User::factory()->create();
        foreach (['/membership', '/credits', '/about', '/faq', '/scanner'] as $path) {
            $response = $this->actingAs($user)->get($path)->assertOk();
            $this->assertUniqueIds($response->getContent());
        }

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get('/admin')->assertOk();
        $this->assertUniqueIds($response->getContent());
    }

    public function test_admin_console_pages_share_accessible_navigation_and_unique_ids(): void
    {
        $admin = User::factory()->admin()->create();
        $managedUser = User::factory()->create();
        $paths = [
            route('admin.dashboard'),
            route('admin.users.index'),
            route('admin.users.show', $managedUser),
            route('admin.plans.index'),
            route('admin.features.index'),
            route('admin.credits.index'),
            route('admin.content.index'),
            route('admin.settings.index'),
            route('admin.audit.index'),
        ];

        foreach (['vi', 'en'] as $locale) {
            foreach ($paths as $path) {
                $response = $this->actingAs($admin)->withSession(['locale' => $locale])->get($path)->assertOk()
                    ->assertSee('data-admin-shell', false)
                    ->assertSee('aria-label=', false)
                    ->assertDontSee('onclick=', false);
                $html = $response->getContent();
                $this->assertUniqueIds($html);
                preg_match('/<aside\b[^>]*data-admin-sidebar[^>]*>(.*?)<\/aside>/s', $html, $sidebar);
                $this->assertNotEmpty($sidebar[1] ?? null, "Admin sidebar is missing for {$path} ({$locale}).");
                $this->assertSame(1, substr_count($sidebar[1], 'aria-current="page"'), "Admin navigation active state is ambiguous for {$path} ({$locale}).");
            }
        }
    }

    public function test_sensitive_admin_user_changes_require_the_shared_confirmation_dialog(): void
    {
        $admin = User::factory()->admin()->create();
        $managedUser = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.show', $managedUser))
            ->assertOk()
            ->assertSee('data-confirm-dialog', false)
            ->assertSee('data-confirm-title', false)
            ->assertSee('data-confirm="'.e(__('platform.confirm_user_update')).'"', false)
            ->assertDontSee('onclick=', false);
    }

    public function test_plan_validation_old_input_is_scoped_to_the_submitted_card(): void
    {
        $admin = User::factory()->admin()->free()->create();
        $free = Plan::where('code', 'free')->firstOrFail();
        $plus = Plan::where('code', 'plus')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.plans.update', $free), [
            'plan_id' => $free->id,
            'name' => 'Edited Free Name',
            'description' => $free->description,
            'monthly_credit_allowance' => -1,
            'history_retention_days' => $free->history_retention_days,
            'is_active' => 1,
        ])->assertSessionHasErrors('monthly_credit_allowance');

        $html = $this->get(route('admin.plans.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'value="Edited Free Name"'));
        $this->assertStringContainsString('value="'.e($plus->name).'"', $html);
    }

    public function test_scan_exports_render_available_locked_and_hidden_states_without_fake_urls(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'completed',
            'stage' => 'completed', 'progress' => 100, 'authorization_confirmed_at' => now(),
        ]);
        Feature::where('code', 'export_csv')->update(['is_enabled' => false, 'is_visible' => true]);
        Feature::where('code', 'export_pdf')->update(['is_visible' => false]);

        $this->actingAs($user)->get(route('scanner.show', $scan))->assertOk()
            ->assertSee(route('scanner.scans.export.json', $scan), false)
            ->assertSee(__('platform.errors.feature_disabled'))
            ->assertDontSee(route('scanner.scans.export.csv', $scan), false)
            ->assertDontSee(route('scanner.scans.export.pdf', $scan), false)
            ->assertDontSee('href="#"', false);
    }

    private function assertUniqueIds(string $html): void
    {
        preg_match_all('/\sid="([^"]+)"/', $html, $matches);
        $this->assertSame($matches[1], array_values(array_unique($matches[1])), 'The rendered page contains duplicate IDs.');
    }
}
