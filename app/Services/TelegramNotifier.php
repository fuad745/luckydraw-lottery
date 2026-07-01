<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendTelegramMessage;

final class TelegramNotifier
{
    /**
     * Milliseconds between queued messages in a broadcast. ~25 msg/s keeps us
     * under Telegram's ~30 msg/s global bulk limit without hitting 429s.
     */
    private const BROADCAST_SPACING_MS = 40;

    /**
     * Queue a single message to one Telegram user.
     *
     * @param  array<string,mixed>|null  $replyMarkup  raw Telegram reply_markup
     */
    public function send(int|string $telegramId, string $message, ?string $type = null, ?int $roundId = null, ?array $replyMarkup = null): void
    {
        // 64-bit ids are stored as strings (see Player::$casts); accept both.
        if (! is_numeric($telegramId) || (int) $telegramId <= 0) {
            return;
        }

        SendTelegramMessage::dispatch($telegramId, $message, $type, $roundId, $replyMarkup);
    }

    /**
     * Queue the same message to many users (de-duplicated).
     *
     * @param  iterable<int>  $telegramIds
     * @param  array<string,mixed>|null  $replyMarkup  raw Telegram reply_markup
     */
    public function broadcast(iterable $telegramIds, string $message, ?string $type = null, ?int $roundId = null, ?array $replyMarkup = null): void
    {
        $seen = [];
        $i = 0;

        foreach ($telegramIds as $id) {
            $id = (int) $id;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            // Stagger dispatch so a large broadcast trickles out under Telegram's
            // rate limit rather than firing all at once and tripping 429s.
            SendTelegramMessage::dispatch($id, $message, $type, $roundId, $replyMarkup)
                ->delay(now()->addMilliseconds($i * self::BROADCAST_SPACING_MS));
            $i++;
        }
    }

    /**
     * Post a public message to a channel/group (no-op if not configured).
     *
     * @param  array<string,mixed>|null  $replyMarkup  raw Telegram reply_markup
     */
    public function toChannel(?string $channelId, string $message, ?string $type = null, ?int $roundId = null, ?array $replyMarkup = null): void
    {
        if (empty($channelId)) {
            return;
        }

        SendTelegramMessage::dispatch($channelId, $message, $type, $roundId, $replyMarkup);
    }
}
