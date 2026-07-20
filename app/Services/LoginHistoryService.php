<?php

namespace App\Services;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoginHistoryService
{
    public function recordLogin(User $user, Request $request): void
    {
        try {
            $history = $user->loginHistories()->create([
                'session_hash' => $this->sessionHash($request),
                'ip_address' => $this->limited($request->ip(), 45),
                'user_agent' => $this->limited($request->userAgent(), 512),
                'login_at' => now(),
            ]);
            $request->session()->put('login_history_id', $history->id);
        } catch (Throwable $exception) {
            Log::warning('Login history could not be recorded.', ['exception' => $exception::class]);
        }
    }

    public function recordLogout(User $user, Request $request): void
    {
        try {
            $historyId = $request->session()->get('login_history_id');
            $query = LoginHistory::query()->whereBelongsTo($user)->whereNull('logout_at');

            if (is_numeric($historyId)) {
                $query->whereKey((int) $historyId);
            } else {
                $query->where('session_hash', $this->sessionHash($request));
            }

            $query->update(['logout_at' => now(), 'updated_at' => now()]);
            $request->session()->forget('login_history_id');
        } catch (Throwable $exception) {
            Log::warning('Login history logout time could not be recorded.', ['exception' => $exception::class]);
        }
    }

    private function sessionHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    private function limited(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
