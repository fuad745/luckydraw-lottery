<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendTelegramMessage;

final class TelegramNotifier
{
    /** Queue a single message to one Telegram user. */
    public function send(int|string $telegramId, string $message, ?string $type = null, ?int $roundId = null): void
    {
        // 64-bit ids are stored as strings (see Player::$casts); accept both.
        if (! is_numeric($telegramId) || (int) $telegramId <= 0) {
            return;
        }

        SendTelegramMessage::dispatch($telegramId, $message, $type, $roundId);
    }

    /**
     * Queue the same message to many users (de-duplicated).
     *
     * @param  iterable<int>  $telegramIds
     */
    public function broadcast(iterable $telegramIds, string $message, ?string $type = null, ?int $roundId = null): void
    {
        $seen = [];

        foreach ($telegramIds as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $this->send($id, $message, $type, $roundId);
        }
    }

    /** Post a public message to a channel/group (no-op if not configured). */
    public function toChannel(?string $channelId, string $message, ?string $type = null, ?int $roundId = null): void
    {
        if (empty($channelId)) {
            return;
        }

        SendTelegramMessage::dispatch($channelId, $message, $type, $roundId);
    }
}
