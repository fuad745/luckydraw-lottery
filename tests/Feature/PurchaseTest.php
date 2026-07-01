<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoundStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\Player;
use App\Models\Round;
use App\Services\LotteryService;
use App\Services\PlayerService;
use App\Services\PurchaseData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private function openRound(int $total = 10): Round
    {
        return Round::factory()->create([
            'total_tickets' => $total,
            'ticket_price' => 50,
            'status' => RoundStatus::Open,
            'auto_draw' => false,
            'allow_half_tickets' => true,
        ]);
    }

    private function pick(int $n, bool $half = false): array
    {
        return ['number' => $n, 'half' => $half];
    }

    public function test_full_pick_on_an_open_half_is_charged_as_a_half(): void
    {
        // Regression: buying a full on a number that only has an open half used
        // to charge full price (2x) while delivering only a half stake.
        Queue::fake();
        $round = $this->openRound();       // price 50
        $this->fundedPlayer(111, 1000);
        $this->fundedPlayer(222, 1000);

        // A opens a half of #5.
        app(LotteryService::class)->purchase($round, new PurchaseData(111, 'A', null, null, [$this->pick(5, true)]));
        // B picks #5 as a FULL, but only the second half is available.
        app(LotteryService::class)->purchase($round, new PurchaseData(222, 'B', null, null, [$this->pick(5, false)]));

        // B pays for the half actually received (25), not the full 50.
        $this->assertSame(975.0, (float) Player::whereKey(222)->value('balance'));
    }

    public function test_it_buys_full_tickets_chosen_on_the_board(): void
    {
        Queue::fake();
        $round = $this->openRound();
        $this->fundedPlayer(111);

        $tickets = app(LotteryService::class)->purchase($round, new PurchaseData(
            buyerTelegramId: 111,
            buyerName: 'Abebe Kebede',
            buyerPhone: '+251911223344',
            picks: [$this->pick(7), $this->pick(3), $this->pick(9)],
        ));

        $this->assertCount(3, $tickets);
        $this->assertEqualsCanonicalizing([3, 7, 9], $tickets->pluck('ticket_number')->all());
        $this->assertSame(150.0, $round->fresh()->prizePool());
        Queue::assertPushed(SendTelegramMessage::class);
    }

    public function test_it_buys_a_half_ticket(): void
    {
        Queue::fake();
        $round = $this->openRound();
        $this->fundedPlayer(222);

        $tickets = app(LotteryService::class)->purchase($round, new PurchaseData(
            buyerTelegramId: 222,
            buyerName: 'Half Buyer',
            buyerPhone: '+251922334455',
            picks: [$this->pick(5, half: true)],
        ));

        $ticket = $tickets->first();
        $this->assertTrue($ticket->is_split);
        $this->assertNull($ticket->co_owner_telegram_id);
        $this->assertSame(0.5, $ticket->fractionSold());
        $this->assertSame(25.0, $round->fresh()->prizePool()); // half price into the pot
    }

    public function test_two_players_can_each_own_half_of_a_number(): void
    {
        Queue::fake();
        $round = $this->openRound();
        $this->fundedPlayer(333);
        $this->fundedPlayer(444);
        $svc = app(LotteryService::class);

        $svc->purchase($round, new PurchaseData(333, 'Owner One', '+251933445566', picks: [$this->pick(4, true)]));
        $tickets = $svc->purchase($round, new PurchaseData(444, 'Owner Two', '+251944556677', picks: [$this->pick(4, true)]));

        $ticket = $tickets->first();
        $this->assertSame('444', $ticket->co_owner_telegram_id); // ids are cast to string (64-bit safe)
        $this->assertSame(1.0, $ticket->fractionSold());
        $this->assertEqualsCanonicalizing([333, 444], array_keys($ticket->holderShares()));
    }

    public function test_it_rejects_buying_a_taken_number(): void
    {
        Queue::fake();
        $round = $this->openRound();
        $this->fundedPlayer(1);
        $this->fundedPlayer(2);
        $svc = app(LotteryService::class);

        $svc->purchase($round, new PurchaseData(1, 'First', '+251900000001', picks: [$this->pick(2)]));

        $this->expectException(ValidationException::class);
        $svc->purchase($round, new PurchaseData(2, 'Second', '+251900000002', picks: [$this->pick(2)]));
    }

    public function test_banned_players_cannot_buy(): void
    {
        Queue::fake();
        $round = $this->openRound();
        $this->fundedPlayer(777)->update(['banned_at' => now()]);

        $this->expectException(ValidationException::class);
        app(LotteryService::class)->purchase($round, new PurchaseData(
            buyerTelegramId: 777,
            buyerName: 'Banned',
            buyerPhone: '+251977000000',
            picks: [$this->pick(1)],
        ));
    }

    public function test_referrer_is_rewarded_on_first_purchase(): void
    {
        Queue::fake();
        app(PlayerService::class)->resolve(555, 'Referrer');
        $referrer = Player::find(555);

        // Funded buyer, already linked to the referrer (linkage happens at first sight).
        $buyer = $this->fundedPlayer(666);
        $buyer->update(['referred_by' => $referrer->referral_code]);

        $round = $this->openRound();
        app(LotteryService::class)->purchase($round, new PurchaseData(
            buyerTelegramId: 666,
            buyerName: 'Referred Buyer',
            buyerPhone: '+251955667788',
            picks: [$this->pick(1)],
            referredByCode: $referrer->referral_code,
        ));

        $this->assertSame(1, $referrer->fresh()->referral_count);
        $this->assertSame(1, $referrer->fresh()->free_tickets);
    }
}
