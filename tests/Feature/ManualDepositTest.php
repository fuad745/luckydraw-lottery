<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Player;
use App\Models\Transaction;
use App\Services\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ManualDepositTest extends TestCase
{
    use RefreshDatabase;

    private const SMS = 'Dear Customer, you have received ETB 250.00 from Abebe. Ref TB1234ABCD. telebirr';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function request(Player $player): Transaction
    {
        return app(DepositService::class)->requestManual($player, 'telebirr', 'Abebe Kebede', '0911223344', self::SMS);
    }

    public function test_manual_request_creates_a_pending_deposit_without_crediting(): void
    {
        $player = Player::factory()->create(['balance' => 0]);

        $tx = $this->request($player);

        $this->assertSame(TransactionStatus::Pending, $tx->status);
        $this->assertSame(TransactionType::Deposit, $tx->type);
        $this->assertSame(250.0, (float) $tx->amount); // parsed from the SMS
        $this->assertSame('TB1234ABCD', $tx->reference);
        $this->assertSame('Abebe Kebede', $tx->meta['name']);
        $this->assertSame(0.0, (float) $player->fresh()->balance);
    }

    public function test_approve_credits_the_wallet_once_and_only_once(): void
    {
        $player = Player::factory()->create(['balance' => 0]);
        $tx = $this->request($player);

        $service = app(DepositService::class);
        $service->approveManual($tx, 250.0);
        $service->approveManual($tx->fresh(), 250.0); // double-click / second admin

        $this->assertSame(250.0, (float) $player->fresh()->balance);
        $this->assertSame(TransactionStatus::Completed, $tx->fresh()->status);
    }

    public function test_reject_frees_the_reference_for_a_retry(): void
    {
        $player = Player::factory()->create(['balance' => 0]);
        $tx = $this->request($player);

        app(DepositService::class)->rejectManual($tx, 'No matching payment');

        $this->assertSame(TransactionStatus::Rejected, $tx->fresh()->status);
        $this->assertSame(0.0, (float) $player->fresh()->balance);

        // The same SMS can be submitted again after a rejection.
        $retry = $this->request($player);
        $this->assertSame(TransactionStatus::Pending, $retry->status);
    }

    public function test_fallback_queues_a_manual_review_when_verification_fails(): void
    {
        config(['lottery.payments.verify_key' => 'test-key']);
        \Illuminate\Support\Facades\Http::fake(['*/verify' => \Illuminate\Support\Facades\Http::response(['success' => false, 'error' => 'Receipt not found'], 404)]);
        $player = Player::factory()->create(['balance' => 0]);

        $result = app(DepositService::class)->depositWithFallback($player, 'telebirr', self::SMS, 'Abebe Kebede', '0911223344');

        $this->assertSame('pending', $result['status']);
        $this->assertSame(TransactionStatus::Pending, $result['tx']->status);
        $this->assertSame(0.0, (float) $player->fresh()->balance);
    }

    public function test_fallback_credits_directly_when_verification_succeeds(): void
    {
        config(['lottery.payments.verify_key' => 'test-key', 'lottery.payments.deposit_accounts' => []]);
        \Illuminate\Support\Facades\Http::fake(['*/verify' => \Illuminate\Support\Facades\Http::response(['success' => true, 'transactionAmount' => 250, 'receiverName' => 'LuckyDraw'])]);
        $player = Player::factory()->create(['balance' => 0]);

        $result = app(DepositService::class)->depositWithFallback($player, 'telebirr', self::SMS, 'Abebe Kebede', '0911223344');

        $this->assertSame('credited', $result['status']);
        $this->assertSame(250.0, (float) $player->fresh()->balance);
    }

    public function test_fallback_surfaces_a_duplicate_reference_instead_of_queueing(): void
    {
        config(['lottery.payments.verify_key' => 'test-key', 'lottery.payments.deposit_accounts' => []]);
        \Illuminate\Support\Facades\Http::fake(['*/verify' => \Illuminate\Support\Facades\Http::response(['success' => true, 'transactionAmount' => 250, 'receiverName' => 'LuckyDraw'])]);
        $player = Player::factory()->create(['balance' => 0]);
        $service = app(DepositService::class);
        $service->depositWithFallback($player, 'telebirr', self::SMS, 'Abebe Kebede', '0911223344');

        $this->expectException(ValidationException::class);
        $service->depositWithFallback($player, 'telebirr', self::SMS, 'Abebe Kebede', '0911223344');
    }

    public function test_the_same_reference_cannot_be_queued_twice(): void
    {
        $a = Player::factory()->create();
        $b = Player::factory()->create();
        $this->request($a);

        $this->expectException(ValidationException::class);
        $this->request($b);
    }
}
