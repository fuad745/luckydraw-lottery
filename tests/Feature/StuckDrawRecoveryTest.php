<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoundStatus;
use App\Models\Round;
use App\Services\LotteryService;
use App\Services\PurchaseData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The scheduler's lottery:check-deadlines run is the safety net for draws whose
 * ProcessDraw queue job was lost (killed worker, cleared jobs table). A round
 * stuck in "Drawing" past the grace window must be completed inline.
 */
final class StuckDrawRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /** Put a round into Drawing with one sold ticket, as a lost job would leave it. */
    private function drawingRound(): Round
    {
        $round = Round::factory()->create([
            'total_tickets' => 3,
            'ticket_price' => 50,
            'status' => RoundStatus::Open,
            'auto_draw' => false,
            'winners_count' => 1,
            'prize_structure' => [['type' => 'percent', 'value' => 100]],
        ]);

        $this->fundedPlayer(9001);
        app(LotteryService::class)->purchase($round, new PurchaseData(
            buyerTelegramId: 9001,
            buyerName: 'Stuck Player',
            buyerPhone: '+251900009001',
            picks: [['number' => 1, 'half' => false]],
        ));

        app(LotteryService::class)->startDraw($round->fresh());

        return $round->fresh();
    }

    public function test_a_stuck_drawing_round_is_completed_by_the_scheduler_command(): void
    {
        Queue::fake();
        $round = $this->drawingRound();
        $this->assertSame(RoundStatus::Drawing, $round->status);

        // Simulate the lost job: age the round past suspense + grace.
        Round::whereKey($round->id)->update(['updated_at' => now()->subMinutes(10)]);

        $this->artisan('lottery:check-deadlines')->assertSuccessful();

        $round->refresh();
        $this->assertSame(RoundStatus::Closed, $round->status);
        $this->assertNotNull($round->drawn_at);
        $this->assertSame(1, $round->winners()->count());
    }

    public function test_a_fresh_drawing_round_is_left_for_the_queue_job(): void
    {
        Queue::fake();
        $round = $this->drawingRound();

        // Still within suspense + grace — the queue job should handle it.
        $this->artisan('lottery:check-deadlines')->assertSuccessful();

        $this->assertSame(RoundStatus::Drawing, $round->fresh()->status);
    }
}
