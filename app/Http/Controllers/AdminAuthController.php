<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

final class AdminAuthController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (request()->session()->get('admin_authenticated') === true) {
            return redirect()->route('admin');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        // Brute-force protection: 5 attempts per minute per IP.
        $key = 'admin-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['username' => "Too many attempts. Try again in {$seconds}s."]);
        }

        if (! $this->validCredentials($credentials['username'], $credentials['password'])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput('username');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate(); // prevent session fixation
        $request->session()->put('admin_authenticated', true);

        return redirect()->intended(route('admin'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }

    private function validCredentials(string $username, string $password): bool
    {
        $expectedUser = (string) config('lottery.admin_panel.username');
        $hash = config('lottery.admin_panel.password_hash');

        // Both checks always run (no short-circuit) before combining.
        $userOk = hash_equals($expectedUser, $username);
        $passOk = ! empty($hash)
            ? Hash::check($password, (string) $hash)                                  // preferred: bcrypt
            : hash_equals((string) config('lottery.admin_panel.password'), $password); // fallback: constant-time plaintext

        return $userOk && $passOk;
    }
}
