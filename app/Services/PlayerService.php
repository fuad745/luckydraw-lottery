<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Str;

final class PlayerService
{
    /**
     * Find or create a player from Telegram identity data, attaching a
     * referral code on first sight and recording who referred them.
     */
    public function resolve(
        int $telegramId,
        string $name,
        ?string $username = null,
        ?string $phone = null,
        ?string $referredByCode = null,
    ): Player {
        $player = Player::find($telegramId);

        if ($player === null) {
            $player = Player::create([
                'telegram_id' => $telegramId,
                'name' => $name,
                'username' => $username,
                'phone' => $phone,
                'referral_code' => $this->uniqueReferralCode(),
                // Never let a player refer themselves; only honour a real, existing code.
                'referred_by' => $this->validReferrer($referredByCode, $telegramId),
            ]);
        } else {
            // Keep the freshest profile details.
            $player->fill(array_filter([
                'name' => $name,
                'username' => $username,
                'phone' => $phone ?: $player->phone,
            ]));
            $player->save();
        }

        return $player;
    }

    public function uniqueReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Player::where('referral_code', $code)->exists());

        return $code;
    }

    private function validReferrer(?string $code, int $telegramId): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $referrer = Player::where('referral_code', $code)->first();

        if ($referrer === null || $referrer->telegram_id === $telegramId) {
            return null;
        }

        return $referrer->referral_code;
    }
}
