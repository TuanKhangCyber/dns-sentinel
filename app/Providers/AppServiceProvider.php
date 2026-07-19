<?php

namespace App\Providers;

use App\Services\Scanner\Contracts\VulnerabilityScannerInterface;
use App\Services\Scanner\NessusApiService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VulnerabilityScannerInterface::class, NessusApiService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
