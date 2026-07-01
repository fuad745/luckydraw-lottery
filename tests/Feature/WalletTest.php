<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Jobs\SendTelegramMessage;
use App\Models\Player;
use App\Services\DepositService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['lottery.payments.verify_key' => 'test-key']);
        config(['lottery.payments.deposit_accounts' => []]); // skip receiver match in tests
    }

    private function fakeVerify(array $body, int $status = 200): void
    {
        Http::fake(['*/verify' => Http::response($body, $status)]);
    }

    public function test_a_verified_deposit_credits_the_wallet(): void
    {
        $player = Player::factory()->create(['balance' => 0]);
        $this->fakeVerify(['success' => true, 'transactionAmount' => 500, 'receiverName' => 'LuckyDraw']);

        $tx = app(DepositService::class)->deposit($player, 'telebirr', 'TRX123');

        $this->assertSame(500.0, (float) $player->fresh()->balance);
        $this->assertSame(TransactionType::Deposit, $tx->type);
        $this->assertSame(500.0, (float) $tx->amount);
    }

    public function test_deposit_can_suppress_the_queued_confirmation(): void
    {
        // The DM deposit flow replies synchronously, so it credits with
        // notify:false and must not also queue the confirmation message.
        $player = Player::factory()->create(['balance' => 0]);
        $this->fakeVerify(['success' => true, 'amount' => 150, 'receiverName' => 'LuckyDraw']);

        $tx = app(DepositService::class)->deposit($player, 'telebirr', 'NODM1', notify: false);

        $this->assertSame(150.0, (float) $tx->amount);
        Queue::assertNotPushed(SendTelegramMessage::class);
    }

    public function test_deposit_extracts_reference_from_a_pasted_cbe_sms(): void
    {
        // A player pastes the whole CBE message; the FT reference is pulled out.
        $player = Player::factory()->create(['balance' => 0]);
        Http::fake(['*/verify' => Http::response(['success' => true, 'amount' => 400])]);

        $sms = 'Dear Customer, ETB 400.00 transferred. Ref FT253089F68Z. '
            .'https://apps.cbe.com.et:100/?id=FT253089F68Z12345678';
        $tx = app(DepositService::class)->deposit($player, 'telebirr', $sms);

        $this->assertSame(400.0, (float) $tx->amount);
        // The pasted link is a CBE receipt, so the provider is auto-corrected.
        $this->assertSame('cbe', $tx->provider);
        $this->assertSame('FT253089F68Z', $tx->reference);
    }

    public function test_a_reference_cannot_be_used_twice(): void
    {
        $player = Player::factory()->create(['balance' => 0]);
        $this->fakeVerify(['success' => true, 'amount' => 200]);

        app(DepositService::class)->deposit($player, 'telebirr', 'DUP1');

        $this->expectException(ValidationException::class);
        app(DepositService::class)->deposit($player, 'telebirr', 'DUP1');
    }

    public function test_a_failed_verification_is_rejected(): void
    {
        $player = Player::factory()->create(['balance' => 0]);
        $this->fakeVerify(['success' => false, 'message' => 'not found']);

        $this->expectException(ValidationException::class);
        app(DepositService::class)->deposit($player, 'telebirr', 'BAD');
    }

    public function test_a_deposit_to_the_wrong_account_is_rejected(): void
    {
        config(['lottery.payments.deposit_accounts' => ['LuckyDraw PLC']]);
        $player = Player::factory()->create(['balance' => 0]);
        $this->fakeVerify(['success' => true, 'amount' => 300, 'receiverName' => 'Someone Else']);

        $this->expectException(ValidationException::class);
        app(DepositService::class)->deposit($player, 'telebirr', 'WRONG');
    }

    public function test_a_pending_or_failed_transaction_status_is_rejected(): void
    {
        $player = Player::factory()->create(['balance' => 0]);
        // Has an amount, but the provider reports it is not settled yet.
        $this->fakeVerify(['amount' => 500, 'status' => 'pending']);

        $this->expectException(ValidationException::class);
        app(DepositService::class)->deposit($player, 'telebirr', 'PENDING1');
    }

    public function test_account_match_normalises_phone_digits(): void
    {
        // Configured local form vs verified international form must still match.
        config(['lottery.payments.deposit_accounts' => ['0912345678']]);
        $player = Player::factory()->create(['balance' => 0]);
        $this->fakeVerify(['success' => true, 'amount' => 250, 'receiverAccountNumber' => '251912345678']);

        $tx = app(DepositService::class)->deposit($player, 'telebirr', 'PHONE1');

        $this->assertSame(250.0, (float) $tx->amount);
    }

    public function test_withdrawal_reserves_funds_and_admin_approves(): void
    {
        $player = Player::factory()->create(['balance' => 100]);
        $service = app(WithdrawalService::class);

        $tx = $service->request($player, 60, 'telebirr', '+251911000000');
        $this->assertSame(40.0, (float) $player->fresh()->balance); // reserved
        $this->assertSame(TransactionStatus::Pending, $tx->status);

        $service->approve($tx);
        $this->assertSame(TransactionStatus::Completed, $tx->fresh()->status);
        $this->assertSame(40.0, (float) $player->fresh()->balance);
    }

    public function test_rejected_withdrawal_is_refunded(): void
    {
        $player = Player::factory()->create(['balance' => 100]);
        $service = app(WithdrawalService::class);

        $tx = $service->request($player, 60, 'telebirr', '+251911000000');
        $service->reject($tx, 'bad details');

        $this->assertSame(100.0, (float) $player->fresh()->balance); // refunded
        $this->assertSame(TransactionStatus::Rejected, $tx->fresh()->status);
        $this->assertDatabaseHas('transactions', ['type' => 'refund', 'telegram_id' => $player->telegram_id]);
    }

    public function test_withdrawal_over_balance_is_blocked(): void
    {
        $player = Player::factory()->create(['balance' => 10]);

        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->request($player, 60, 'telebirr', '+251911000000');
    }

    public function test_repeat_withdrawals_to_the_same_account_are_allowed(): void
    {
        // Regression: the unique(provider,reference) index used to crash the
        // second payout to the same account.
        $player = Player::factory()->create(['balance' => 500]);
        $service = app(WithdrawalService::class);

        $service->request($player, 100, 'telebirr', '0912345678');
        $service->request($player->fresh(), 100, 'telebirr', '0912345678');

        $this->assertSame(300.0, (float) $player->fresh()->balance);
        $this->assertSame(2, $player->transactions()->where('type', 'withdrawal')->count());
    }

    public function test_deposit_fails_closed_in_production_without_configured_accounts(): void
    {
        $this->app['env'] = 'production';
        config(['lottery.payments.deposit_accounts' => []]);
        $player = Player::factory()->create(['balance' => 0]);
        $this->fakeVerify(['success' => true, 'amount' => 500, 'receiverName' => 'Someone Else']);

        try {
            app(DepositService::class)->deposit($player, 'telebirr', 'PRODREF1');
            $this->fail('Expected the deposit to be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('temporarily unavailable', collect($e->errors())->flatten()->first());
        }

        $this->assertSame(0.0, (float) $player->fresh()->balance);
    }
}
