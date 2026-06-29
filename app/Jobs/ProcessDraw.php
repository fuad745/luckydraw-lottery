<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Round;
use App\Services\LotteryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs after the suspense delay: reveals and announces the winner.
 */
final class ProcessDraw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // a draw must never run twice

    public function __construct(public readonly int $roundId) {}

    public function handle(LotteryService $lottery): void
    {
        $round = Round::find($this->roundId);

        if ($round === null) {
            return;
        }

        $lottery->performDraw($round);
    }
}
