<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Player;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class WalletService
{
    /** Add funds to a player's wallet and record the ledger entry. */
    public function credit(int|string $telegramId, float $amount, TransactionType $type, array $attrs = []): Transaction
    {
        return $this->move($telegramId, abs($amount), $type, true, $attrs);
    }

    /**
     * Remove funds from a player's wallet (balance-checked) and record it.
     *
     * @throws InsufficientBalanceException
     */
    public function debit(int|string $telegramId, float $amount, TransactionType $type, array $attrs = []): Transaction
    {
        return $this->move($telegramId, abs($amount), $type, false, $attrs);
    }

    public function balance(int|string $telegramId): float
    {
        return (float) (Player::whereKey($telegramId)->value('balance') ?? 0);
    }

    // 64-bit Telegram ids are stored/cast as strings (see Player::$casts), so the
    // key is accepted as int|string and only ever used for keyed lookups below.
    private function move(int|string $telegramId, float $amount, TransactionType $type, bool $isCredit, array $attrs): Transaction
    {
        $amountCents = Money::toCents($amount);
        $amount = Money::toAmount($amountCents);

        return DB::transaction(function () use ($telegramId, $amount, $amountCents, $type, $isCredit, $attrs): Transaction {
            $player = Player::whereKey($telegramId)->lockForUpdate()->firstOrFail();

            $balanceCents = Money::toCents($player->balance);

            if (! $isCredit && $balanceCents < $amountCents) {
                throw new InsufficientBalanceException(
                    'Insufficient balance. You have '.number_format((float) $player->balance, 2).' but need '.number_format($amount, 2).'.'
                );
            }

            $player->balance = Money::toAmount($isCredit ? $balanceCents + $amountCents : $balanceCents - $amountCents);
            $player->save();

            return Transaction::create([
                'telegram_id' => $telegramId,
                'type' => $type,
                'status' => $attrs['status'] ?? TransactionStatus::Completed,
                'amount' => $amount,
                'balance_after' => $player->balance,
                'provider' => $attrs['provider'] ?? null,
                'reference' => $attrs['reference'] ?? null,
                // Only deposits enforce a unique reference; other types (esp.
                // withdrawals, whose reference is the payout account) leave this
                // null so they never collide on the unique index.
                'deposit_reference' => $type === TransactionType::Deposit ? ($attrs['reference'] ?? null) : null,
                'round_id' => $attrs['round_id'] ?? null,
                'note' => $attrs['note'] ?? null,
                'meta' => $attrs['meta'] ?? null,
                'processed_at' => now(),
            ]);
        });
    }
}
