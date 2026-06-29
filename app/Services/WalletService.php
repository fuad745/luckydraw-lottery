<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Player;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class WalletService
{
    /** Add funds to a player's wallet and record the ledger entry. */
    public function credit(int $telegramId, float $amount, TransactionType $type, array $attrs = []): Transaction
    {
        return $this->move($telegramId, abs($amount), $type, true, $attrs);
    }

    /**
     * Remove funds from a player's wallet (balance-checked) and record it.
     *
     * @throws InsufficientBalanceException
     */
    public function debit(int $telegramId, float $amount, TransactionType $type, array $attrs = []): Transaction
    {
        return $this->move($telegramId, abs($amount), $type, false, $attrs);
    }

    public function balance(int $telegramId): float
    {
        return (float) (Player::whereKey($telegramId)->value('balance') ?? 0);
    }

    private function move(int $telegramId, float $amount, TransactionType $type, bool $isCredit, array $attrs): Transaction
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($telegramId, $amount, $type, $isCredit, $attrs): Transaction {
            $player = Player::whereKey($telegramId)->lockForUpdate()->firstOrFail();

            if (! $isCredit && (float) $player->balance < $amount) {
                throw new InsufficientBalanceException(
                    'Insufficient balance. You have '.number_format((float) $player->balance, 2).' but need '.number_format($amount, 2).'.'
                );
            }

            $player->balance = round((float) $player->balance + ($isCredit ? $amount : -$amount), 2);
            $player->save();

            return Transaction::create([
                'telegram_id' => $telegramId,
                'type' => $type,
                'status' => $attrs['status'] ?? TransactionStatus::Completed,
                'amount' => $amount,
                'balance_after' => $player->balance,
                'provider' => $attrs['provider'] ?? null,
                'reference' => $attrs['reference'] ?? null,
                'round_id' => $attrs['round_id'] ?? null,
                'note' => $attrs['note'] ?? null,
                'meta' => $attrs['meta'] ?? null,
                'processed_at' => now(),
            ]);
        });
    }
}
