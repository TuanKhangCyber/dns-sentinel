<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_view_update_delete_or_cancel_a_scan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $owner->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'quick_tcp', 'status' => 'queued', 'stage' => 'queued',
            'scanner_type' => 'nmap', 'authorization_confirmed_at' => now(),
        ]);

        foreach (['view', 'update', 'delete', 'cancel'] as $ability) {
            $this->assertTrue($owner->can($ability, $scan));
            $this->assertFalse($other->can($ability, $scan));
        }

        $scan->update(['status' => 'completed']);
        $this->assertFalse($owner->can('cancel', $scan->fresh()));
    }
}
