<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Money;

final class PrizeCalculator
{
    /**
     * Split a prize pool across ordered tiers; whatever is left goes to the house.
     *
     * Tier shapes:
     *   ['type' => 'percent', 'value' => 70]   // 70% of the pool
     *   ['type' => 'ticket_price']             // exactly one ticket price
     *   ['type' => 'fixed', 'value' => 100]    // a fixed amount
     *
     * @param  array<int,array<string,mixed>>  $structure
     * @return array{tiers: array<int,float>, admin: float}
     */
    public function distribute(float $pot, float $ticketPrice, array $structure): array
    {
        $potCents = Money::toCents($pot);
        $priceCents = Money::toCents($ticketPrice);

        $raw = [];
        foreach ($structure as $tier) {
            $raw[] = max(0, $this->tierCents($potCents, $priceCents, $tier));
        }

        $total = array_sum($raw);

        // Never pay out more than the pot — scale down proportionally if over-configured.
        if ($total > $potCents && $total > 0) {
            $raw = Money::allocate($potCents, array_map(fn ($c) => (float) $c, $raw));
        }

        $tiers = array_map(fn ($c) => Money::toAmount($c), $raw);
        $adminCents = max(0, $potCents - array_sum($raw));

        return ['tiers' => $tiers, 'admin' => Money::toAmount($adminCents)];
    }

    private function tierCents(int $potCents, int $priceCents, array $tier): int
    {
        return match ($tier['type'] ?? 'percent') {
            'ticket_price' => $priceCents,
            'fixed' => Money::toCents($tier['value'] ?? 0),
            default => (int) round($potCents * ((float) ($tier['value'] ?? 0)) / 100),
        };
    }

    /**
     * Sensible default tier list for a given winner count
     * (1st 70%, 2nd 15%, 3rd one ticket price, rest small).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function defaultStructure(int $winners): array
    {
        $base = [
            ['type' => 'percent', 'value' => 70],
            ['type' => 'percent', 'value' => 15],
            ['type' => 'ticket_price'],
            ['type' => 'percent', 'value' => 2],
            ['type' => 'percent', 'value' => 1],
        ];

        if ($winners <= 1) {
            return [['type' => 'percent', 'value' => 100]];
        }

        return array_slice($base, 0, min($winners, count($base)));
    }
}
