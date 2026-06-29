<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';     // awaiting admin action (withdrawals)
    case Completed = 'completed'; // settled
    case Rejected = 'rejected';   // declined / failed (funds refunded)

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'text-amber-400',
            self::Completed => 'text-emerald-400',
            self::Rejected => 'text-rose-400',
        };
    }
}
