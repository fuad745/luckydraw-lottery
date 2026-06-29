<?php

namespace App\Providers;

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
        //
    }
}
