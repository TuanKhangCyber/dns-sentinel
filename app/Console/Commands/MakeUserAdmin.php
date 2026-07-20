<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MakeUserAdmin extends Command
{
    protected $signature = 'user:make-admin {email}';

    protected $description = 'Promote an existing user to administrator without changing credentials';

    public function handle(AuditService $audit): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }
        $promoted = DB::transaction(function () use ($email, $audit): bool {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->firstOrFail();
            if ($user->isAdmin()) {
                return false;
            }
            $before = ['role' => $user->role];
            $user->update(['role' => 'admin']);
            $audit->record(null, 'user.role_changed_by_command', $user, $before, ['role' => 'admin']);

            return true;
        }, 3);
        if (! $promoted) {
            $this->info('User is already an administrator.');

            return self::SUCCESS;
        }
        $this->info('Administrator role granted to '.$user->email.'.');

        return self::SUCCESS;
    }
}
