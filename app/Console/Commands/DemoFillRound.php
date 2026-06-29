<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Round;
use App\Services\LotteryService;
use App\Services\PlayerService;
use App\Services\PurchaseData;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class DemoFillRound extends Command
{
    protected $signature = 'lottery:demo-fill {--round= : Round id (defaults to the current open round)} {--players=8 : Number of simulated buyers}';

    protected $description = 'Simulate buyers filling a round (great for testing the draw)';

    public function handle(LotteryService $lottery): int
    {
        $round = $this->option('round')
            ? Round::find((int) $this->option('round'))
            : Round::current();

        if ($round === null || ! $round->isOpen()) {
            $this->error('No open round to fill. Create one in /admin first.');

            return self::FAILURE;
        }

        $players = max(2, (int) $this->option('players'));
        $this->info("Filling “{$round->title}” with up to {$players} demo buyers…");

        // Demo buyers spend from their wallet — give them funds first.
        $playerService = app(PlayerService::class);
        for ($i = 1; $i <= $players; $i++) {
            $playerService->resolve(800_000 + $i, "Demo Buyer {$i}")->update(['balance' => 1_000_000]);
        }

        $guard = 0;
        $bought = 0;
        $max = $round->total_tickets * 3;

        while ($round->isOpen() && ! $round->isFull() && $guard++ < $max) {
            $board = $lottery->boardState($round);

            $candidates = [];
            foreach ($board as $n => $cell) {
                if ($cell['state'] === 'free') {
                    $candidates[] = ['number' => $n, 'half' => $round->allow_half_tickets && random_int(0, 2) === 0, 'owner' => null];
                } elseif ($cell['state'] === 'half_open') {
                    $candidates[] = ['number' => $n, 'half' => true, 'owner' => $cell['owner_id']];
                }
            }

            if ($candidates === []) {
                break;
            }

            $pick = $candidates[array_rand($candidates)];

            // Choose a buyer that isn't the existing half-owner.
            $buyerId = 800_000 + random_int(1, $players);
            if ($pick['owner'] === $buyerId) {
                $buyerId = 800_000 + ($buyerId - 800_000) % $players + 1;
            }

            try {
                $lottery->purchase($round, new PurchaseData(
                    buyerTelegramId: $buyerId,
                    buyerName: 'Demo Buyer '.($buyerId - 800_000),
                    buyerPhone: '+25190'.str_pad((string) $buyerId, 7, '0', STR_PAD_LEFT),
                    picks: [['number' => $pick['number'], 'half' => $pick['half']]],
                ));
                $bought++;
            } catch (ValidationException) {
                // Number was just taken — try another.
            }

            $round->refresh();
        }

        $round->refresh();
        $this->info("Done. {$bought} stake(s) bought · sold {$round->soldUnits()}/{$round->total_tickets} · status: {$round->status->value}.");

        if ($round->status->value === 'drawing') {
            $this->comment('Round is now drawing — run `php artisan queue:work` to reveal the winners.');
        }

        return self::SUCCESS;
    }
}
