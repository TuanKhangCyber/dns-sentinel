<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'user:make-admin {email}';

    protected $description = 'Promote an existing user to administrator without changing credentials';

    public function handle(AuditService $audit): int
    {
        $user = User::whereRaw('LOWER(email) = ?', [strtolower((string) $this->argument('email'))])->first();
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }
        if ($user->isAdmin()) {
            $this->info('User is already an administrator.');

            return self::SUCCESS;
        }
        $before = ['role' => $user->role];
        $user->update(['role' => 'admin']);
        $audit->record(null, 'user.role_changed_by_command', $user, $before, ['role' => 'admin']);
        $this->info('Administrator role granted to '.$user->email.'.');

        return self::SUCCESS;
    }
}
