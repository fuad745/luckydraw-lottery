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
 * Auto-restart: clones a finished round into a fresh open one after a delay.
 */
final class StartNextRound implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $previousRoundId) {}

    public function handle(LotteryService $lottery): void
    {
        // Don't start a parallel round if an admin already opened one.
        if (Round::current() !== null) {
            return;
        }

        $previous = Round::find($this->previousRoundId);
        if ($previous === null) {
            return;
        }

        $lottery->cloneRound($previous);
    }
}
