<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Telegram\TelegramAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTelegramAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(TelegramAuth::class)->isAdmin()) {
            abort(403, 'Admin access only.');
        }

        return $next($request);
    }
}
