<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Round;
use App\Models\Ticket;
use Illuminate\Support\Collection;

final class DrawService
{
    /**
     * Pick up to $count distinct winning tickets using a cryptographically
     * secure RNG (random_int). Order is the placement (1st, 2nd, …).
     *
     * @return Collection<int, Ticket>
     */
    public function pickWinners(Round $round, int $count): Collection
    {
        $tickets = $round->tickets()->get()->values();

        if ($tickets->isEmpty()) {
            return collect();
        }

        // Partial Fisher–Yates shuffle using a CSPRNG.
        $items = $tickets->all();
        $n = count($items);
        $take = min($count, $n);

        for ($i = 0; $i < $take; $i++) {
            $j = random_int($i, $n - 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return collect(array_slice($items, 0, $take));
    }

    /** Convenience: single winner (used by simple/legacy flows). */
    public function pickWinner(Round $round): ?Ticket
    {
        return $this->pickWinners($round, 1)->first();
    }
}
