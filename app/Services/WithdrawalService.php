<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Player;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

final class WithdrawalService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly TelegramNotifier $notifier,
    ) {}

    /**
     * Request a payout. Funds are reserved (debited) immediately and the
     * request is queued for an admin to pay out and approve.
     *
     * @throws ValidationException
     */
    public function request(Player $player, float $amount, string $provider, string $account): Transaction
    {
        $amount = round($amount, 2);
        $min = (float) config('lottery.payments.min_withdraw', 50);

        if ($player->isBanned()) {
            throw ValidationException::withMessages(['amount' => 'Your account is suspended.']);
        }
        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => "Minimum withdrawal is {$min} ".config('lottery.currency').'.']);
        }
        if (trim($account) === '') {
            throw ValidationException::withMessages(['account' => 'Enter the account/phone to receive the payout.']);
        }

        try {
            $tx = $this->wallet->debit($player->telegram_id, $amount, TransactionType::Withdrawal, [
                'status' => TransactionStatus::Pending, // funds reserved, awaiting payout
                'provider' => strtolower($provider),
                'reference' => trim($account),
                'note' => 'Withdrawal request',
            ]);
        } catch (InsufficientBalanceException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        $this->notifier->send(
            $player->telegram_id,
            "⏳ <b>Withdrawal requested</b>\n{$amount} ".config('lottery.currency').
            " to {$account}.\nIt will be paid out shortly. Balance: ".number_format((float) $player->fresh()->balance, 2),
            'withdrawal',
        );

        // Ping admins so they can action it.
        foreach ((array) config('lottery.admin_telegram_ids', []) as $adminId) {
            $this->notifier->send((int) $adminId, "💸 New withdrawal request: {$amount} ".config('lottery.currency')." (#{$tx->id}) from {$player->name}.", 'withdrawal_admin');
        }

        return $tx;
    }

    /** Admin marks the payout as sent. */
    public function approve(Transaction $tx): void
    {
        if ($tx->type !== TransactionType::Withdrawal || $tx->status !== TransactionStatus::Pending) {
            return;
        }

        $tx->update(['status' => TransactionStatus::Completed, 'processed_at' => now()]);

        $this->notifier->send(
            (int) $tx->telegram_id,
            "✅ <b>Withdrawal paid!</b>\n{$tx->amount} ".config('lottery.currency')." sent to {$tx->reference}. 🎉",
            'withdrawal',
        );
    }

    /** Admin rejects: refund the reserved amount back to the wallet. */
    public function reject(Transaction $tx, ?string $reason = null): void
    {
        if ($tx->type !== TransactionType::Withdrawal || $tx->status !== TransactionStatus::Pending) {
            return;
        }

        $this->wallet->credit((int) $tx->telegram_id, (float) $tx->amount, TransactionType::Refund, [
            'note' => 'Refund for rejected withdrawal #'.$tx->id.($reason ? ': '.$reason : ''),
        ]);

        $tx->update(['status' => TransactionStatus::Rejected, 'processed_at' => now(), 'note' => $reason]);

        $this->notifier->send(
            (int) $tx->telegram_id,
            "↩️ <b>Withdrawal declined</b>\nYour {$tx->amount} ".config('lottery.currency').' has been refunded to your balance.'.($reason ? "\nReason: ".$reason : ''),
            'withdrawal',
        );
    }
}
