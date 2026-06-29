<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Telegram\TelegramAuth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    private const SUPPORTED = ['en', 'am'];

    public function handle(Request $request, Closure $next): Response
    {
        $player = app(TelegramAuth::class)->player();

        $locale = $player?->locale
            ?? $request->session()->get('locale')
            ?? config('app.locale', 'en');

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
