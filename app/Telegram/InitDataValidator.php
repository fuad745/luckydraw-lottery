<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * Validates the `initData` string handed to a Telegram Mini App, per
 * https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
final class InitDataValidator
{
    /**
     * @return array<string,mixed>|null Parsed fields (with `user` decoded) when valid, else null.
     */
    public function validate(string $initData, string $botToken, int $maxAgeSeconds = 86400): ?array
    {
        if ($initData === '' || $botToken === '') {
            return null;
        }

        parse_str($initData, $pairs);

        if (! isset($pairs['hash']) || ! is_string($pairs['hash'])) {
            return null;
        }

        $hash = $pairs['hash'];
        unset($pairs['hash']);

        // Build the data-check-string: "key=value" sorted by key, joined by \n.
        ksort($pairs);
        $dataCheckString = collect($pairs)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($computedHash, $hash)) {
            return null;
        }

        // Reject stale payloads (replay protection).
        if (isset($pairs['auth_date']) && $maxAgeSeconds > 0) {
            $age = time() - (int) $pairs['auth_date'];
            if ($age > $maxAgeSeconds) {
                return null;
            }
        }

        if (isset($pairs['user']) && is_string($pairs['user'])) {
            $pairs['user'] = json_decode($pairs['user'], true) ?: null;
        }

        return $pairs;
    }
}
