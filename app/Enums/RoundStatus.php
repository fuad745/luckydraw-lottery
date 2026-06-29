<?php

declare(strict_types=1);

namespace App\Enums;

enum RoundStatus: string
{
    case Open = 'open';          // accepting ticket purchases
    case Drawing = 'drawing';    // tickets locked, draw in progress (suspense)
    case Closed = 'closed';      // winner drawn, round finished
    case Cancelled = 'cancelled'; // round cancelled by admin

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Drawing => 'Drawing',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Tailwind text colour for badges. */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'text-emerald-400',
            self::Drawing => 'text-amber-400',
            self::Closed => 'text-slate-400',
            self::Cancelled => 'text-rose-400',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Open || $this === self::Drawing;
    }
}
