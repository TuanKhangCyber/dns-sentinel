<?php

namespace Tests\Feature;

use App\Exceptions\FeatureAccessException;
use App\Jobs\RunNmapScanJob;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Scan;
use App\Models\User;
use App\Services\CreditService;
use App\Services\Scanner\NmapScannerService;
use App\Services\Scanner\ScanRiskService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;
use Mockery;
use Tests\TestCase;

class CreditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_and_refund_are_atomic_and_idempotent(): void
    {
        $user = User::factory()->create();
        CreditWallet::create(['user_id' => $user->id, 'balance' => 10]);
        $service = app(CreditService::class);
        $first = $service->charge($user, 'nmap_scan', 3, Scan::class, 100);
        $second = $service->charge($user, 'nmap_scan', 3, Scan::class, 100);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(7, $user->wallet->fresh()->balance);
        $this->assertDatabaseCount('credit_transactions', 1);
        $service->refund($first, 'failed');
        $service->refund($first, 'failed again');
        $this->assertSame(10, $user->wallet->fresh()->balance);
        $this->assertDatabaseCount('credit_transactions', 2);
    }

    public function test_balance_never_becomes_negative(): void
    {
        $user = User::factory()->create();
        CreditWallet::create(['user_id' => $user->id, 'balance' => 2]);
        try {
            app(CreditService::class)->charge($user, 'nmap_scan', 3, Scan::class, 101);
            $this->fail('Charge should fail.');
        } catch (FeatureAccessException $exception) {
            $this->assertSame('insufficient_credits', $exception->errorCode);
        }
        $this->assertSame(2, $user->wallet->fresh()->balance);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_ledger_prevents_hard_deleting_a_user_with_transactions(): void
    {
        $user = User::factory()->create();
        CreditWallet::create(['user_id' => $user->id, 'balance' => 5]);
        app(CreditService::class)->charge($user, 'dns_lookup', 1, Scan::class, 999);

        try {
            $user->delete();
            $this->fail('A user with ledger transactions must not be hard deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
            $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id]);
        }
    }

    public function test_double_scan_submit_charges_only_once(): void
    {
        config()->set('scanner.allowlist', ['8.8.8.8']);
        Bus::fake();
        $user = User::factory()->create();
        CreditWallet::create(['user_id' => $user->id, 'balance' => 20]);
        $payload = ['target' => '8.8.8.8', 'profile' => 'quick_tcp', 'authorization_confirmed' => true];
        $this->actingAs($user)->postJson('/scanner/scans', $payload)->assertAccepted();
        $this->postJson('/scanner/scans', $payload)->assertConflict();
        $this->assertSame(15, $user->wallet->fresh()->balance);
        $this->assertSame(1, CreditTransaction::where('type', 'usage')->count());
        Bus::assertDispatchedTimes(RunNmapScanJob::class, 1);
    }

    public function test_dispatch_failure_refunds_scan_charge(): void
    {
        config()->set('scanner.allowlist', ['8.8.8.8']);
        $user = User::factory()->create();
        CreditWallet::create(['user_id' => $user->id, 'balance' => 10]);
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('queue secret detail'));
        $this->actingAs($user)->postJson('/scanner/scans', ['target' => '8.8.8.8', 'profile' => 'quick_tcp', 'authorization_confirmed' => true])->assertStatus(503);
        $this->assertSame(10, $user->wallet->fresh()->balance);
        $this->assertSame(1, CreditTransaction::where('type', 'refund')->count());
    }

    public function test_user_credit_page_never_accepts_another_user_id(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        CreditWallet::create(['user_id' => $owner->id, 'balance' => 77]);
        CreditWallet::create(['user_id' => $other->id, 'balance' => 5]);
        $this->actingAs($other)->get('/credits?user_id='.$owner->id)->assertOk()->assertSee('5')->assertDontSee('77');
    }

    public function test_failed_scanner_job_refunds_once(): void
    {
        $user = User::factory()->create();
        CreditWallet::create(['user_id' => $user->id, 'balance' => 10]);
        $scan = Scan::create(['user_id' => $user->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4', 'profile' => 'quick_tcp', 'scanner_type' => 'nmap', 'status' => 'queued', 'stage' => 'queued', 'authorization_confirmed_at' => now()]);
        $charge = app(CreditService::class)->charge($user, 'nmap_scan', 5, Scan::class, $scan->id);
        $scan->update(['credit_transaction_id' => $charge->id]);
        $scanner = Mockery::mock(NmapScannerService::class);
        $scanner->shouldReceive('run')->once()->andThrow(new \RuntimeException('worker failed'));
        try {
            (new RunNmapScanJob($scan->id))->handle($scanner, new ScanRiskService);
        } catch (\RuntimeException) {
        }
        $this->assertSame('failed', $scan->fresh()->status);
        $this->assertSame(10, $user->wallet->fresh()->balance);
        $this->assertSame(1, CreditTransaction::where('type', 'refund')->count());
    }

    public function test_monthly_reset_is_idempotent_uses_effective_plan_and_skips_suspended_users(): void
    {
        Date::setTestNow('2026-07-01 00:15:00');
        $free = User::factory()->free()->create();
        $plus = User::factory()->create();
        $expired = User::factory()->create(['membership_expires_at' => now()->subMinute()]);
        $suspended = User::factory()->suspended()->create();
        foreach ([$free, $plus, $expired, $suspended] as $user) {
            CreditWallet::create(['user_id' => $user->id, 'balance' => 0]);
        }

        $this->artisan('credits:reset-monthly')->assertSuccessful();
        $this->artisan('credits:reset-monthly')->assertSuccessful();

        $this->assertSame(25, $free->wallet->fresh()->balance);
        $this->assertSame(500, $plus->wallet->fresh()->balance);
        $this->assertSame(25, $expired->wallet->fresh()->balance);
        $this->assertSame('free', $expired->fresh()->plan->code);
        $this->assertSame(0, $suspended->wallet->fresh()->balance);
        $this->assertSame(3, CreditTransaction::where('type', 'monthly_reset')->count());
        $this->assertSame(3, CreditTransaction::where('idempotency_key', 'like', 'monthly-reset:%:2026-07')->count());
    }
}
