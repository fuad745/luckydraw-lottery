<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Phone;
use App\Telegram\TelegramAuth;

/**
 * Shared "share your Telegram contact" handling. The Mini App calls
 * `window.luckyRequestContact()` which fires Telegram's native phone prompt;
 * the number is then sent here via `$wire.savePhone(...)`. (Telegram also
 * forwards the contact to the bot webhook as a backup — see routes/telegram.php.)
 */
trait SharesContact
{
    /** Persist a phone number to the current player. Returns the saved value or null. */
    protected function persistPhone(string $phone): ?string
    {
        $player = app(TelegramAuth::class)->player();
        if ($player === null) {
            return null;
        }

        $normalized = Phone::normalize($phone);
        if ($normalized === null) {
            return null;
        }

        $player->update(['phone' => $normalized]);

        return $normalized;
    }
}
