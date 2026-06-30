<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoundStatus;
use App\Enums\TransactionStatus;
use App\Jobs\ProcessDraw;
use App\Models\Player;
use App\Models\Round;
use App\Services\LotteryService;
use App\Services\PurchaseData;
use App\Services\ReferralService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression tests for the money-critical races: each transition must be a
 * locked compare-and-set so a duplicate trigger can never double-pay.
 */
final class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function fillRound(LotteryService $svc, Round $round): void
    {
        for ($n = 1; $n <= $round->total_tickets; $n++) {
            $this->fundedPlayer(2000 + $n);
            $svc->purchase($round, new PurchaseData(
                buyerTelegramId: 2000 + $n,
                buyerName: "Player {$n}",
                buyerPhone: '+25190001'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                picks: [['number' => $n, 'half' => false]],
            ));
        }
    }

    public function test_a_second_process_draw_does_not_pay_winners_twice(): void
    {
        Queue::fake();
        $round = Round::factory()->create([
            'total_tickets' => 5,
            'ticket_price' => 100,
            'status' => RoundStatus::Open,
            'auto_draw' => true,
            'winners_count' => 1,
            'prize_structure' => [['type' => 'percent', 'value' => 100]],
        ]);

        $lottery = app(LotteryService::class);
        $this->fillRound($lottery, $round); // → status Drawing, ProcessDraw queued

        // Two workers both hold a model that still reads "Drawing" (the race).
        $stale = $round->fresh();
        $this->assertSame(RoundStatus::Drawing, $stale->status);

        $lottery->performDraw($round->fresh());      // the real draw → Closed + payout
        $paidOnce = (float) Player::sum('total_winnings');
        $balanceOnce = (float) Player::sum('balance');

        // The duplicate job runs on its stale "Drawing" model — must be a no-op.
        $lottery->performDraw($stale);

        $this->assertSame(RoundStatus::Closed, $round->fresh()->status);
        $this->assertSame($paidOnce, (float) Player::sum('total_winnings'), 'winnings double-credited');
        $this->assertSame($balanceOnce, (float) Player::sum('balance'), 'wallet double-credited');
        $this->assertSame(1, Player::where('total_wins', '>', 0)->count(), 'win recorded twice');
    }

    public function test_concurrent_start_draw_dispatches_only_one_process_draw(): void
    {
        Queue::fake();
        $round = Round::factory()->create(['total_tickets' => 3, 'status' => RoundStatus::Open, 'auto_draw' => false]);

        // Sell one ticket so the round has units but is not full (no auto-trigger).
        $this->fundedPlayer(3001);
        app(LotteryService::class)->purchase($round, new PurchaseData(3001, 'Buyer', '+251900030001', picks: [['number' => 1, 'half' => false]]));

        $lottery = app(LotteryService::class);
        $stale1 = $round->fresh();
        $stale2 = $round->fresh();

        $lottery->startDraw($stale1);
        $lottery->startDraw($stale2); // loses the compare-and-set

        Queue::assertPushed(ProcessDraw::class, 1);
        $this->assertSame(RoundStatus::Drawing, $round->fresh()->status);
    }

    public function test_rejecting_a_withdrawal_twice_refunds_only_once(): void
    {
        Queue::fake();
        $player = $this->fundedPlayer(4001, 500);
        $svc = app(WithdrawalService::class);

        $tx = $svc->request($player, 100, 'telebirr', '+251900040001'); // reserves → balance 400
        $this->assertSame(400.0, (float) $player->fresh()->balance);

        $svc->reject($tx);            // refund once → balance 500
        $svc->reject($tx);            // stale Pending in memory → must be a no-op

        $this->assertSame(500.0, (float) $player->fresh()->balance, 'refund credited twice');
        $this->assertSame(TransactionStatus::Rejected, $tx->fresh()->status);
    }

    public function test_approving_then_rejecting_a_withdrawal_does_not_refund(): void
    {
        Queue::fake();
        $player = $this->fundedPlayer(4002, 500);
        $svc = app(WithdrawalService::class);

        $tx = $svc->request($player, 100, 'telebirr', '+251900040002'); // balance 400
        $svc->approve($tx);   // marks paid
        $svc->reject($tx);    // stale Pending model — must not refund a paid-out withdrawal

        $this->assertSame(400.0, (float) $player->fresh()->balance);
        $this->assertSame(TransactionStatus::Completed, $tx->fresh()->status);
    }

    public function test_referral_reward_is_granted_only_once(): void
    {
        $referrer = Player::factory()->create(['referral_code' => 'REFCODE1', 'referral_count' => 0, 'free_tickets' => 0]);
        $buyer = Player::factory()->create(['referred_by' => 'REFCODE1', 'total_tickets_bought' => 0]);

        $svc = app(ReferralService::class);
        $svc->rewardOnFirstPurchase($buyer, 0);
        $svc->rewardOnFirstPurchase($buyer->fresh(), 0); // duplicate "first purchase"

        $referrer->refresh();
        $this->assertSame(1, (int) $referrer->referral_count);
        $this->assertSame(1, (int) $referrer->free_tickets);
    }

    public function test_self_referral_earns_no_reward(): void
    {
        $buyer = Player::factory()->create([
            'referral_code' => 'SELFONE1',
            'referred_by' => 'SELFONE1',
            'total_tickets_bought' => 0,
            'free_tickets' => 0,
            'referral_count' => 0,
        ]);

        app(ReferralService::class)->rewardOnFirstPurchase($buyer, 0);

        $buyer->refresh();
        $this->assertSame(0, (int) $buyer->free_tickets);
        $this->assertSame(0, (int) $buyer->referral_count);
    }
}
