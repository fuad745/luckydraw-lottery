<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Telegram\TelegramAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the game behind registration: an authenticated Telegram user who has
 * not shared their phone yet is sent to the registration screen. Unauthenticated
 * requests (e.g. a plain browser with no initData) pass through untouched — the
 * Telegram-only layout gate handles those.
 */
final class EnsureRegistered
{
    public function handle(Request $request, Closure $next): Response
    {
        $player = app(TelegramAuth::class)->player();

        if ($player !== null && empty($player->phone)) {
            return redirect()->route('register');
        }

        return $next($request);
    }
}
