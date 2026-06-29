<?php

declare(strict_types=1);

namespace App\Services;

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
        $raw = [];
        foreach ($structure as $tier) {
            $raw[] = max(0.0, $this->tierAmount($pot, $ticketPrice, $tier));
        }

        $total = array_sum($raw);

        // Never pay out more than the pot — scale down proportionally if over-configured.
        if ($total > $pot && $total > 0) {
            $scale = $pot / $total;
            $raw = array_map(fn ($a) => $a * $scale, $raw);
        }

        $tiers = array_map(fn ($a) => round($a, 2), $raw);
        $admin = round(max(0.0, $pot - array_sum($tiers)), 2);

        return ['tiers' => $tiers, 'admin' => $admin];
    }

    private function tierAmount(float $pot, float $ticketPrice, array $tier): float
    {
        return match ($tier['type'] ?? 'percent') {
            'ticket_price' => $ticketPrice,
            'fixed' => (float) ($tier['value'] ?? 0),
            default => $pot * ((float) ($tier['value'] ?? 0)) / 100,
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
