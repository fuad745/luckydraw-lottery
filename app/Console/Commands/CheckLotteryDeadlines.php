<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoundStatus;
use App\Models\Round;
use App\Services\LotteryService;
use Illuminate\Console\Command;

final class CheckLotteryDeadlines extends Command
{
    protected $signature = 'lottery:check-deadlines';

    protected $description = 'Trigger the draw for any open round whose deadline has passed';

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

        if ($rounds->isEmpty()) {
            $this->line('No rounds past their deadline.');
        }

        return self::SUCCESS;
    }
}
