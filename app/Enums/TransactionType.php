<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Purchase = 'purchase';
    case Winning = 'winning';
    case Refund = 'refund';
    case Adjustment = 'adjustment';

    /** Does this type add to (credit) or subtract from (debit) the balance? */
    public function isCredit(): bool
    {
        return match ($this) {
            self::Deposit, self::Winning, self::Refund => true,
            self::Withdrawal, self::Purchase => false,
            self::Adjustment => true, // sign decided by caller
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::Purchase => 'Ticket purchase',
            self::Winning => 'Prize',
            self::Refund => 'Refund',
            self::Adjustment => 'Adjustment',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Deposit => '⬇️',
            self::Withdrawal => '⬆️',
            self::Purchase => '🎟',
            self::Winning => '🏆',
            self::Refund => '↩️',
            self::Adjustment => '⚙️',
        };
    }
}
