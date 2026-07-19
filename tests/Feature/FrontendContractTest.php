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
