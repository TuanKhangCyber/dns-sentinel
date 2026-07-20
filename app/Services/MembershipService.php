<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MembershipService
{
    public function provision(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $planCode = app(SystemSettingService::class)->get('default_plan', config('features.default_plan', 'free'));
            $plan = Plan::query()->where('code', $planCode)->where('is_active', true)->first()
                ?? Plan::query()->where('code', 'free')->where('is_active', true)->firstOrFail();
            $user->update([
                'plan_id' => $plan->id, 'membership_status' => 'active',
                'membership_started_at' => now(), 'membership_expires_at' => null, 'status' => 'active',
            ]);
            $wallet = $user->wallet()->firstOrCreate([], ['balance' => 0]);
            $credits = max(0, (int) app(SystemSettingService::class)->get('default_signup_credits', config('features.signup_credits', 10)));
            if ($credits > 0 && ! CreditTransaction::where('idempotency_key', 'signup:'.$user->id)->exists()) {
                $wallet->increment('balance', $credits);
                CreditTransaction::create([
                    'user_id' => $user->id, 'amount' => $credits, 'type' => 'grant',
                    'idempotency_key' => 'signup:'.$user->id, 'description' => 'Initial signup credits.',
                ]);
            }
        });
    }

    public function effectivePlan(User $user): Plan
    {
        $user->loadMissing('plan');
        if ($user->membership_expires_at?->isPast() || $user->membership_status !== 'active' || ! $user->plan?->is_active) {
            $free = Plan::query()->where('code', 'free')->where('is_active', true)->firstOrFail();
            if ($user->plan_id !== $free->id || $user->membership_status !== 'active') {
                $user->forceFill(['plan_id' => $free->id, 'membership_status' => 'active', 'membership_expires_at' => null])->save();
                $user->setRelation('plan', $free);
            }
        }
        if (! $user->plan) {
            throw new RuntimeException('No active membership plan is available.');
        }

        return $user->plan;
    }

    public function changePlan(User $user, Plan $plan, ?\DateTimeInterface $expiresAt = null): void
    {
        if (! $plan->is_active) {
            throw new RuntimeException('The selected plan is inactive.');
        }
        $user->update([
            'plan_id' => $plan->id, 'membership_status' => 'active',
            'membership_started_at' => now(), 'membership_expires_at' => $expiresAt,
        ]);
    }
}
