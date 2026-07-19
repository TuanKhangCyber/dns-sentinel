<?php

namespace App\Policies;

use App\Models\Scan;
use App\Models\User;

class ScanPolicy
{
    public function view(User $user, Scan $scan): bool
    {
        return $scan->user_id === $user->id;
    }

    public function update(User $user, Scan $scan): bool
    {
        return $scan->user_id === $user->id;
    }

    public function delete(User $user, Scan $scan): bool
    {
        return $scan->user_id === $user->id;
    }

    public function cancel(User $user, Scan $scan): bool
    {
        return $scan->user_id === $user->id && ! $scan->isTerminal();
    }
}
