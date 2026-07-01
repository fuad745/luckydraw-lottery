<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tiny key/value store for runtime-editable configuration that must survive
 * without touching .env (e.g. the admin panel credentials).
 */
final class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Read a setting, falling back to $default (and never throwing on a missing table). */
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $value = self::query()->where('key', $key)->value('value');
        } catch (\Throwable) {
            // Table not migrated yet — behave as if unset so auth still works.
            return $default;
        }

        return $value !== null ? (string) $value : $default;
    }

    /** Create or update a setting. */
    public static function put(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
