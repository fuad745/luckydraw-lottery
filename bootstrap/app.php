<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the Telegram Mini App user, then apply their language.
        $middleware->web(append: [
            \App\Http\Middleware\ResolveTelegramUser::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'tg.admin' => \App\Http\Middleware\EnsureTelegramAdmin::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        // The Telegram webhook is server-to-server; exclude it from CSRF.
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
