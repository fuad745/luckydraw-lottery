<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
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
}
