<?php

namespace App\Services;

use App\Exceptions\FeatureAccessException;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function charge(User $user, string $featureCode, int $cost, string $referenceType, int|string $referenceId): ?CreditTransaction
    {
        if ($cost <= 0) {
            return null;
        }
        $key = 'usage:'.$user->id.':'.$featureCode.':'.$referenceType.':'.$referenceId;

        return DB::transaction(function () use ($user, $featureCode, $cost, $referenceType, $referenceId, $key) {
            $existing = CreditTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $wallet = $this->lockedWallet($user);
            $updated = CreditWallet::query()->whereKey($wallet->id)->where('balance', '>=', $cost)->decrement('balance', $cost);
            if ($updated !== 1) {
                throw new FeatureAccessException('insufficient_credits', 403, __('platform.errors.insufficient_credits'));
            }
            try {
                return CreditTransaction::create([
                    'user_id' => $user->id, 'amount' => -$cost, 'type' => 'usage', 'feature_code' => $featureCode,
                    'reference_type' => $referenceType, 'reference_id' => (int) $referenceId,
                    'idempotency_key' => $key, 'description' => 'Feature usage: '.$featureCode,
                ]);
            } catch (QueryException $exception) {
                throw $exception;
            }
        }, 3);
    }

    public function refund(?CreditTransaction $transaction, string $reason): ?CreditTransaction
    {
        if (! $transaction || $transaction->type !== 'usage' || $transaction->amount >= 0) {
            return null;
        }

        return DB::transaction(function () use ($transaction, $reason) {
            $key = 'refund:'.$transaction->id;
            $existing = CreditTransaction::where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $wallet = $this->lockedWallet($transaction->user);
            $amount = abs($transaction->amount);
            $wallet->increment('balance', $amount);

            return CreditTransaction::create([
                'user_id' => $transaction->user_id, 'amount' => $amount, 'type' => 'refund',
                'feature_code' => $transaction->feature_code, 'reference_type' => $transaction->reference_type,
                'reference_id' => $transaction->reference_id, 'idempotency_key' => $key,
                'description' => mb_substr($reason, 0, 1000),
            ]);
        }, 3);
    }

    public function adjust(User $user, int $amount, User $actor, string $reason): CreditTransaction
    {
        return DB::transaction(function () use ($user, $amount, $actor, $reason) {
            $wallet = $this->lockedWallet($user);
            if ($amount < 0 && $wallet->balance < abs($amount)) {
                throw new FeatureAccessException('insufficient_credits', 422, __('platform.errors.insufficient_credits'));
            }
            $wallet->increment('balance', $amount);

            return CreditTransaction::create([
                'user_id' => $user->id, 'amount' => $amount, 'type' => 'adjustment', 'created_by' => $actor->id,
                'idempotency_key' => 'adjustment:'.str()->uuid(), 'description' => mb_substr($reason, 0, 1000),
            ]);
        }, 3);
    }

    public function resetMonthly(User $user): CreditTransaction
    {
        return DB::transaction(function () use ($user) {
            $plan = app(MembershipService::class)->effectivePlan($user);
            $wallet = $this->lockedWallet($user);
            $month = now()->format('Y-m');
            $key = 'monthly-reset:'.$user->id.':'.$month;
            if ($existing = CreditTransaction::where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $amount = $plan->monthly_credit_allowance - $wallet->balance;
            $wallet->update(['balance' => $plan->monthly_credit_allowance, 'last_monthly_reset_at' => now()]);

            return CreditTransaction::create([
                'user_id' => $user->id, 'amount' => $amount, 'type' => 'monthly_reset',
                'idempotency_key' => $key, 'description' => 'Monthly plan credit reset.',
            ]);
        }, 3);
    }

    private function lockedWallet(User $user): CreditWallet
    {
        $user->wallet()->firstOrCreate([], ['balance' => app(MembershipService::class)->effectivePlan($user)->monthly_credit_allowance]);

        return CreditWallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }
}
