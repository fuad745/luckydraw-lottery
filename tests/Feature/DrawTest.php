<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoundStatus;
use App\Jobs\ProcessDraw;
use App\Jobs\StartNextRound;
use App\Models\Player;
use App\Models\Round;
use App\Services\LotteryService;
use App\Services\PurchaseData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DrawTest extends TestCase
{
    use RefreshDatabase;

    /** Buy every number in the round as full tickets, one per player. */
    private function fillRound(LotteryService $svc, Round $round): void
    {
        for ($n = 1; $n <= $round->total_tickets; $n++) {
            $this->fundedPlayer(1000 + $n);
            $svc->purchase($round, new PurchaseData(
                buyerTelegramId: 1000 + $n,
                buyerName: "Player {$n}",
                buyerPhone: '+25190000'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                picks: [['number' => $n, 'half' => false]],
            ));
        }
    }

    public function test_selling_the_last_ticket_locks_round_and_schedules_draw(): void
    {
        Queue::fake();
        $round = Round::factory()->create(['total_tickets' => 3, 'status' => RoundStatus::Open, 'auto_draw' => true]);

        $this->fillRound(app(LotteryService::class), $round);

        $this->assertSame(RoundStatus::Drawing, $round->fresh()->status);
        Queue::assertPushed(ProcessDraw::class);
    }

    public function test_multi_winner_draw_distributes_tiered_prizes(): void
    {
        Queue::fake();
        $round = Round::factory()->create([
            'total_tickets' => 10,
            'ticket_price' => 100,
            'status' => RoundStatus::Open,
            'auto_draw' => true,
            'winners_count' => 3,
            'prize_structure' => [
                ['type' => 'percent', 'value' => 70],
                ['type' => 'percent', 'value' => 15],
                ['type' => 'ticket_price'],
            ],
            'auto_restart' => true,
            'restart_delay_minutes' => 5,
        ]);

        $lottery = app(LotteryService::class);
        $this->fillRound($lottery, $round); // pot = 1000

        $winners = $lottery->performDraw($round->fresh());

        $this->assertCount(3, $winners);
        $round->refresh();
        $this->assertSame(RoundStatus::Closed, $round->status);

        // 70% / 15% / ticket-price = 700 / 150 / 100, leaving 50 to admin.
        $this->assertEqualsCanonicalizing([700.0, 150.0, 100.0], $winners->map(fn ($w) => (float) $w->prize_amount)->all());
        $this->assertSame(50.0, (float) $round->admin_cut);
        $this->assertSame(1, $winners->first()->win_rank);

        // Each distinct winner got a recorded win.
        $this->assertSame(3, Player::where('total_wins', '>', 0)->count());

        // Lifetime winnings credited = pot minus the house cut (1000 - 50).
        $this->assertSame(950.0, (float) Player::sum('total_winnings'));

        Queue::assertPushed(StartNextRound::class);
    }

    public function test_split_winner_shares_the_tier_and_house_keeps_the_open_half(): void
    {
        Queue::fake();
        $round = Round::factory()->create([
            'total_tickets' => 1,
            'ticket_price' => 100,
            'status' => RoundStatus::Open,
            'auto_draw' => false,
            'winners_count' => 1,
            'prize_structure' => [['type' => 'percent', 'value' => 100]],
            'allow_half_tickets' => true,
        ]);

        $lottery = app(LotteryService::class);
        $this->fundedPlayer(7);
        // Only one half of the single number is sold → pot = 50.
        $lottery->purchase($round, new PurchaseData(7, 'Solo Half', '+251900000007', picks: [['number' => 1, 'half' => true]]));
        $lottery->startDraw($round->fresh());

        $winners = $lottery->performDraw($round->fresh());
        $winner = $winners->first();
        $round->refresh();

        // Pot 50, tier 100% = 50; holder owns half so gets 25, house keeps the other 25.
        $this->assertSame(50.0, (float) $winner->prize_amount);
        $this->assertSame(25.0, (float) $round->admin_cut);
    }
}
