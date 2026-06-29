<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Telegram\InitDataValidator;
use App\Telegram\TelegramAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Telegram Mini App user for the request:
 *  1. Validate fresh `initData` from the X-Telegram-Init-Data header (every
 *     Livewire request carries it) or the `_auth` query param (first load).
 *  2. Fall back to the previously validated user stored in the session.
 *  3. In local dev, optionally impersonate via DEV_TELEGRAM_ID for browser testing.
 */
final class ResolveTelegramUser
{
    public function __construct(private readonly InitDataValidator $validator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $auth = app(TelegramAuth::class);
        $token = (string) config('lottery.bot_token');

        $initData = $request->header('X-Telegram-Init-Data')
            ?? $request->input('_auth');

        $user = null;

        if ($initData && $token !== '') {
            $data = $this->validator->validate($initData, $token);
            if ($data !== null && isset($data['user'])) {
                $user = $data['user'];
                session(['tg_user' => $user]);
            }
        }

        if ($user === null && session()->has('tg_user')) {
            $user = session('tg_user');
        }

        if ($user === null && app()->environment('local') && env('DEV_TELEGRAM_ID')) {
            $user = [
                'id' => (int) env('DEV_TELEGRAM_ID'),
                'first_name' => env('DEV_TELEGRAM_NAME', 'Dev Tester'),
                'username' => 'devtester',
                // Lets you test the buy flow in a normal browser (no Telegram contact prompt).
                'phone' => env('DEV_TELEGRAM_PHONE', '+251900000000'),
            ];
        }

        $auth->setUser($user);

        return $next($request);
    }
}
