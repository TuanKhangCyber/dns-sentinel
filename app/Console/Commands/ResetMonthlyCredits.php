<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Console\Command;

class ResetMonthlyCredits extends Command
{
    protected $signature = 'credits:reset-monthly';

    protected $description = 'Idempotently reset active user wallets to their monthly plan allowance';

    public function handle(CreditService $credits): int
    {
        User::query()->where('status', 'active')->orderBy('id')->chunkById(100, function ($users) use ($credits) {
            foreach ($users as $user) {
                $credits->resetMonthly($user);
            }
        });
        $this->info('Monthly credit reset completed.');

        return self::SUCCESS;
    }
}
