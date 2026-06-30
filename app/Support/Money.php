<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Integer-cents money math. All arithmetic that combines or splits amounts
 * should happen in integer minor units (cents) and only convert back to a
 * 2-decimal major-unit value at the boundary — this removes the float drift
 * that creeps in when you add/scale floats and round repeatedly.
 *
 * Storage stays `decimal(14,2)` (exact at rest); this guards the PHP side.
 */
final class Money
{
    /** Major-unit amount → integer cents (round half-up to the nearest cent). */
    public static function toCents(int|float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Integer cents → 2-decimal major-unit float. */
    public static function toAmount(int $cents): float
    {
        return $cents / 100;
    }

    /**
     * Split `$totalCents` across the given weights with the largest-remainder
     * method, so the parts sum back to exactly `$totalCents` (no lost/leaked
     * cent). Weights need not sum to 1; they are treated proportionally.
     *
     * @param  array<int,float>  $weights
     * @return array<int,int> cents per weight, in the same order
     */
    public static function allocate(int $totalCents, array $weights): array
    {
        $weightSum = array_sum($weights);
        if ($totalCents <= 0 || $weightSum <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $floors = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $i => $w) {
            $exact = $totalCents * $w / $weightSum;
            $floors[$i] = (int) floor($exact);
            $remainders[$i] = $exact - $floors[$i];
            $allocated += $floors[$i];
        }

        // Hand out the leftover cents to the largest fractional remainders first.
        $leftover = $totalCents - $allocated;
        arsort($remainders);
        foreach (array_keys($remainders) as $i) {
            if ($leftover <= 0) {
                break;
            }
            $floors[$i]++;
            $leftover--;
        }

        ksort($floors);

        return $floors;
    }
}
