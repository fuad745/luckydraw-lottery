<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;

final class ReferralService
{
    /**
     * Credit the referrer's invite count the first time a referred player buys
     * a ticket. Counting on first purchase (not signup) keeps the leaderboard
     * honest — only invites who actually play count. No free tickets or other
     * monetary reward is granted. Idempotent per player via the one-shot
     * `referral_rewarded_at` claim.
     */
    public function rewardOnFirstPurchase(Player $buyer, int $previousTicketCount): void
    {
        if ($previousTicketCount > 0 || $buyer->referred_by === null) {
            return; // not their first purchase, or they were not referred
        }

        // Atomic one-shot claim: only the first concurrent caller flips
        // null → now() and proceeds, so the referrer is never counted twice.
        $claimed = Player::whereKey($buyer->telegram_id)
            ->whereNull('referral_rewarded_at')
            ->update(['referral_rewarded_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $referrer = Player::where('referral_code', $buyer->referred_by)
            ->where('telegram_id', '!=', $buyer->telegram_id) // no self-referral
            ->first();

        $referrer?->increment('referral_count');
    }
}
