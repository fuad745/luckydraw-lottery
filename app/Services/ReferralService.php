<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Player;

final class ReferralService
{
    /**
     * Reward the referrer the first time a referred player buys a ticket.
     * Idempotent per player via the one-shot `referral_rewarded` flag we
     * derive from total_tickets_bought (reward only on the very first buy).
     */
    public function rewardOnFirstPurchase(Player $buyer, int $previousTicketCount): void
    {
        if ($previousTicketCount > 0 || $buyer->referred_by === null) {
            return; // not their first purchase, or they were not referred
        }

        $referrer = Player::where('referral_code', $buyer->referred_by)->first();

        if ($referrer === null) {
            return;
        }

        $reward = (int) config('lottery.referral_reward_tickets', 1);

        $referrer->increment('referral_count');
        if ($reward > 0) {
            $referrer->increment('free_tickets', $reward);
        }
    }
}
