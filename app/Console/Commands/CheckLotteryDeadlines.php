<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoundStatus;
use App\Models\Round;
use App\Services\LotteryService;
use Illuminate\Console\Command;

final class CheckLotteryDeadlines extends Command
{
    /**
     * Grace period before a "Drawing" round counts as stuck. Generous on
     * purpose: the queue worker runs once a minute on shared hosting, so a
     * healthy draw can legitimately take suspense + ~60s to land.
     */
    private const STUCK_GRACE_SECONDS = 300;

    protected $signature = 'lottery:check-deadlines';

    protected $description = 'Trigger draws for rounds past their deadline, and recover draws stuck by a lost queue job';

    public function handle(LotteryService $lottery): int
    {
        $rounds = Round::where('status', RoundStatus::Open->value)
            ->whereNotNull('draw_deadline')
            ->where('draw_deadline', '<=', now())
            ->get();

        foreach ($rounds as $round) {
            $this->info("Deadline reached for round #{$round->id} ({$round->title}) — starting draw.");
            $lottery->startDraw($round);
        }

        $recovered = $this->recoverStuckDraws($lottery);

        if ($rounds->isEmpty() && $recovered === 0) {
            $this->line('No rounds past their deadline.');
        }

        return self::SUCCESS;
    }

    /**
     * Self-healing: if a ProcessDraw job was ever lost (worker killed mid-run,
     * jobs table cleared, job failed its single try), the round would sit in
     * "Drawing" forever with players' money locked. Finish those draws inline —
     * performDraw() re-checks the status under a row lock, so racing a
     * late-arriving queue job is harmless (one of them no-ops).
     */
    private function recoverStuckDraws(LotteryService $lottery): int
    {
        $suspense = (int) config('lottery.draw_suspense_seconds', 10);
        $cutoff = now()->subSeconds($suspense + self::STUCK_GRACE_SECONDS);

        // updated_at was last touched by the Open→Drawing transition; nothing
        // else writes the round row while it is drawing.
        $stuck = Round::where('status', RoundStatus::Drawing->value)
            ->where('updated_at', '<=', $cutoff)
            ->get();

        foreach ($stuck as $round) {
            $this->warn("Round #{$round->id} ({$round->title}) stuck in Drawing — completing the draw now.");
            $lottery->performDraw($round);
        }

        return $stuck->count();
    }
}
