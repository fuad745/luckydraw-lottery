<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Player;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
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
        // Lock + re-check so two admins clicking at once can't both act on it.
        $claimed = DB::transaction(function () use ($tx): bool {
            $locked = Transaction::whereKey($tx->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->type !== TransactionType::Withdrawal || $locked->status !== TransactionStatus::Pending) {
                return false;
            }

            $locked->update(['status' => TransactionStatus::Completed, 'processed_at' => now()]);

            return true;
        });

        if (! $claimed) {
            return;
        }

        $this->notifier->send(
            (int) $tx->telegram_id,
            "✅ <b>Withdrawal paid!</b>\n{$tx->amount} ".config('lottery.currency')." sent to {$tx->reference}. 🎉",
            'withdrawal',
        );
    }

    /** Admin rejects: refund the reserved amount back to the wallet. */
    public function reject(Transaction $tx, ?string $reason = null): void
    {
        // Lock + re-check so the refund credit can only ever happen once, even if
        // two admins reject simultaneously.
        $claimed = DB::transaction(function () use ($tx, $reason): bool {
            $locked = Transaction::whereKey($tx->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->type !== TransactionType::Withdrawal || $locked->status !== TransactionStatus::Pending) {
                return false;
            }

            $this->wallet->credit((int) $locked->telegram_id, (float) $locked->amount, TransactionType::Refund, [
                'note' => 'Refund for rejected withdrawal #'.$locked->id.($reason ? ': '.$reason : ''),
            ]);

            $locked->update(['status' => TransactionStatus::Rejected, 'processed_at' => now(), 'note' => $reason]);

            return true;
        });

        if (! $claimed) {
            return;
        }

        $this->notifier->send(
            (int) $tx->telegram_id,
            "↩️ <b>Withdrawal declined</b>\nYour {$tx->amount} ".config('lottery.currency').' has been refunded to your balance.'.($reason ? "\nReason: ".$reason : ''),
            'withdrawal',
        );
    }
}
