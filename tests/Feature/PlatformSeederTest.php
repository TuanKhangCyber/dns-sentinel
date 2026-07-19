<?php

namespace Tests\Feature;

use App\Models\FaqItem;
use App\Models\Feature;
use App\Models\Plan;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_seeder_is_idempotent_and_preserves_admin_overrides(): void
    {
        $feature = Feature::where('code', 'security_headers')->firstOrFail();
        $feature->update(['credit_cost' => 99, 'is_enabled' => false]);
        $faq = FaqItem::where('code', 'default_faq_1')->firstOrFail();
        $faq->update(['question' => ['vi' => 'Đã chỉnh', 'en' => 'Edited by admin']]);
        $counts = [Plan::count(), Feature::count(), DB::table('feature_plan')->count(), FaqItem::count()];

        $this->seed(PlatformSeeder::class);
        $this->seed(PlatformSeeder::class);

        $this->assertSame($counts, [Plan::count(), Feature::count(), DB::table('feature_plan')->count(), FaqItem::count()]);
        $this->assertSame(99, $feature->fresh()->credit_cost);
        $this->assertFalse($feature->fresh()->is_enabled);
        $this->assertSame('Edited by admin', $faq->fresh()->question['en']);
    }
}
