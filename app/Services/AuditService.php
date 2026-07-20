<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'secret_key',
        'api_key',
        'access_key',
        'private_key',
        'remember_token',
        'g-recaptcha-response',
        'recaptcha_response',
        'authorization',
        'cookie',
    ];

    public function record(?User $actor, string $action, Model|string|null $target, array $before = [], array $after = [], ?Request $request = null): AuditLog
    {
        $targetType = $target instanceof Model ? $target::class : (is_string($target) ? $target : null);
        $targetId = $target instanceof Model ? $target->getKey() : null;

        return AuditLog::create([
            'actor_id' => $actor?->id, 'action' => $action, 'target_type' => $targetType, 'target_id' => $targetId,
            'before' => $this->sanitize($before), 'after' => $this->sanitize($after),
            'ip_address' => $request?->ip(), 'user_agent' => mb_substr((string) $request?->userAgent(), 0, 500),
        ]);
    }

    public function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalizedKey = str_replace(['-', '.', ' '], '_', strtolower((string) $key));
            if (collect(self::SENSITIVE_KEYS)->contains(fn ($blockedKey) => str_contains($normalizedKey, $blockedKey))) {
                unset($values[$key]);
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }
}
