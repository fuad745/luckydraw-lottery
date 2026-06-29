<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the browser-only operator panel. Independent of Telegram auth.
 */
final class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('admin_authenticated') !== true) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
