<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_render_consistent_vietnamese_content_and_accessible_controls(): void
    {
        $this->withSession(['locale' => 'vi'])->get('/login')->assertOk()
            ->assertSee('lang="vi"', false)
            ->assertSee(__('ui.login'))
            ->assertSee('aria-label="'.__('ui.theme').'"', false);

        $this->withSession(['locale' => 'vi'])->get('/faq')->assertOk()
            ->assertSee(__('platform.faq_intro'))
            ->assertSee('aria-label="'.__('platform.search_faq').'"', false)
            ->assertSee('aria-label="'.__('platform.category').'"', false);
    }

    public function test_guest_pages_render_consistent_english_content(): void
    {
        $this->withSession(['locale' => 'en'])->get('/register')->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Create an account to start investigating DNS.')
            ->assertSee('Dark mode');

        $this->withSession(['locale' => 'en'])->get('/about')->assertOk()
            ->assertSee('Authorized use only')
            ->assertSee('Acknowledgements');
    }

    public function test_authenticated_dns_and_scanner_pages_render_localized_accessible_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['locale' => 'vi'])->get('/dns')->assertOk()
            ->assertSee(__('ui.security_operations'))
            ->assertSee('aria-label="'.__('ui.security_results').'"', false)
            ->assertSee('aria-label="'.__('ui.filter_subdomains').'"', false);

        $this->actingAs($user)->withSession(['locale' => 'en'])->get('/scanner')->assertOk()
            ->assertSee('Authorized security assessment')
            ->assertSee('example.com / 8.8.8.8 / CIDR');
    }

    public function test_admin_navigation_and_forms_follow_the_selected_locale(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['locale' => 'vi'])->get('/admin/features')->assertOk()
            ->assertSee('Tính năng')
            ->assertSee('Thêm metadata tính năng')
            ->assertSee('Chi phí credits');

        $this->actingAs($admin)->withSession(['locale' => 'en'])->get('/admin/settings')->assertOk()
            ->assertSee('Settings')
            ->assertSee('Maintenance notice')
            ->assertSee('Login protection');
    }
}
