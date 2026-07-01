<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

/**
 * Single source of truth for the browser admin-panel credentials.
 *
 * Credentials are seeded from config/.env (ADMIN_PANEL_USERNAME / _PASSWORD /
 * _PASSWORD_HASH) but can be overridden at runtime from the admin Settings page,
 * which stores them in the `settings` table. DB values win when present.
 */
final class AdminCredentials
{
    private const KEY_USERNAME = 'admin_username';

    private const KEY_PASSWORD_HASH = 'admin_password_hash';

    /** The effective admin username (DB override, else config). */
    public function username(): string
    {
        $stored = Setting::get(self::KEY_USERNAME);

        return $stored !== null && $stored !== '' ? $stored : (string) config('lottery.admin_panel.username');
    }

    /** Whether the given username + password pair is valid (constant-time). */
    public function matches(string $username, string $password): bool
    {
        // Compute both sides regardless so timing doesn't reveal which failed.
        $userOk = hash_equals($this->username(), $username);
        $passOk = $this->verifyPassword($password);

        return $userOk && $passOk;
    }

    /** Verify a password against the effective secret (DB hash → config hash → plaintext). */
    public function verifyPassword(string $password): bool
    {
        $dbHash = Setting::get(self::KEY_PASSWORD_HASH);
        if ($dbHash !== null && $dbHash !== '') {
            return Hash::check($password, $dbHash);
        }

        $configHash = config('lottery.admin_panel.password_hash');
        if (! empty($configHash)) {
            return Hash::check($password, (string) $configHash);
        }

        return hash_equals((string) config('lottery.admin_panel.password'), $password);
    }

    /** Persist a new username and/or password (only non-empty values are changed). */
    public function update(?string $username, ?string $newPassword): void
    {
        if ($username !== null && $username !== '') {
            Setting::put(self::KEY_USERNAME, $username);
        }

        if ($newPassword !== null && $newPassword !== '') {
            Setting::put(self::KEY_PASSWORD_HASH, Hash::make($newPassword));
        }
    }
}
