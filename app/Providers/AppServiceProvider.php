<?php

namespace App\Providers;

use App\Services\PaymentSettings;
use App\Telegram\TelegramAuth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One TelegramAuth instance per request lifecycle.
        $this->app->scoped(TelegramAuth::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Apply admin-editable payment overrides on top of config/.env.
        $this->app->make(PaymentSettings::class)->apply();
    }
}
