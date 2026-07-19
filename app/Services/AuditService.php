<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function record(?User $actor, string $action, Model|string|null $target, array $before = [], array $after = [], ?Request $request = null): AuditLog
    {
        $targetType = $target instanceof Model ? $target::class : (is_string($target) ? $target : null);
        $targetId = $target instanceof Model ? $target->getKey() : null;

        return AuditLog::create([
            'actor_id' => $actor?->id, 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId,
            'before' => $this->filter($before), 'after' => $this->filter($after),
            'ip_address' => $request?->ip(), 'user_agent' => mb_substr((string) $request?->userAgent(), 0, 500),
        ]);
    }

    private function filter(array $values): array
    {
        $blocked = ['password', 'password_confirmation', 'token', 'secret', 'secret_key', 'api_key', 'remember_token'];
        foreach ($values as $key => $value) {
            if (collect($blocked)->contains(fn ($blockedKey) => str_contains(strtolower((string) $key), $blockedKey))) {
                unset($values[$key]);
            } elseif (is_array($value)) {
                $values[$key] = $this->filter($value);
            }
        }

        return $values;
    }
}
