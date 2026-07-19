<?php

namespace App\Services;

use App\Exceptions\FeatureAccessException;
use App\Models\Feature;
use App\Models\User;

class FeatureAccessService
{
    public function __construct(private readonly FeatureRegistry $registry, private readonly MembershipService $memberships) {}

    public function status(User $user, string $code): array
    {
        if ($user->isSuspended()) {
            return $this->denied($code, 'account_suspended', 403);
        }
        $plan = $this->memberships->effectivePlan($user);
        if (! $plan->is_active) {
            return $this->denied($code, 'upgrade_required', 403, false, $plan->code);
        }
        if (! $this->registry->has($code)) {
            return $this->denied($code, 'feature_unregistered', 403);
        }
        $feature = Feature::query()->where('code', $code)->with('plans')->first();
        if (! $feature || ! $feature->is_enabled) {
            return $this->denied($code, 'feature_disabled', 403, $feature?->is_visible ?? false);
        }
        $mapping = $feature->plans->firstWhere('id', $plan->id)?->pivot;
        if (! $mapping || ! $mapping->is_enabled) {
            return $this->denied($code, 'upgrade_required', 403, $feature->is_visible, $plan->code);
        }
        if (! $this->dependencyReady($this->registry->get($code)['dependency'] ?? null)) {
            return $this->denied($code, 'service_unavailable', 503, $feature->is_visible, $plan->code);
        }
        $cost = $mapping->credit_cost_override ?? $feature->credit_cost;
        $balance = $user->wallet?->balance ?? $plan->monthly_credit_allowance;
        if ($balance < $cost) {
            return $this->denied($code, 'insufficient_credits', 403, $feature->is_visible, $plan->code, $cost);
        }

        return ['allowed' => true, 'code' => $code, 'reason' => null, 'http_status' => 200, 'visible' => $feature->is_visible, 'plan' => $plan->code, 'cost' => $cost];
    }

    public function authorize(User $user, string $code): array
    {
        $status = $this->status($user, $code);
        if (! $status['allowed']) {
            throw new FeatureAccessException($status['reason'], $status['http_status'], __('platform.errors.'.$status['reason']));
        }

        return $status;
    }

    public function canUse(User $user, string $code): bool
    {
        return $this->status($user, $code)['allowed'];
    }

    private function denied(string $code, string $reason, int $status, bool $visible = false, ?string $plan = null, int $cost = 0): array
    {
        return ['allowed' => false, 'code' => $code, 'reason' => $reason, 'http_status' => $status, 'visible' => $visible, 'plan' => $plan, 'cost' => $cost];
    }

    private function dependencyReady(?string $dependency): bool
    {
        return match ($dependency) {
            'nmap' => filled(config('scanner.nmap_binary')) && config('scanner.allowlist') !== [],
            'nessus' => config('scanner.nessus.enabled') && filled(config('scanner.nessus.url'))
                && filled(config('scanner.nessus.access_key')) && filled(config('scanner.nessus.secret_key')),
            default => true,
        };
    }
}
