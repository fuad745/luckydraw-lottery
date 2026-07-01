<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Immutable description of a single ticket-purchase transaction.
 *
 * Tickets are chosen on the board. Each "pick" is a number plus whether the
 * buyer wants a half (0.5) or a full (1.0) stake in it.
 */
final readonly class PurchaseData
{
    /**
     * @param  array<int,array{number:int,half:bool}>  $picks
     */
    public function __construct(
        public int|string $buyerTelegramId,
        public string $buyerName,
        public ?string $buyerPhone = null,
        public ?string $buyerUsername = null,
        public array $picks = [],
        public ?string $referredByCode = null,
    ) {}

    /** Normalise raw [number => isHalf] / list input into clean picks. */
    public static function picksFromBoard(array $fullNumbers, array $halfNumbers): array
    {
        $picks = [];
        foreach (array_unique(array_map('intval', $fullNumbers)) as $n) {
            $picks[$n] = ['number' => $n, 'half' => false];
        }
        foreach (array_unique(array_map('intval', $halfNumbers)) as $n) {
            // A full pick wins over a half pick for the same number.
            $picks[$n] ??= ['number' => $n, 'half' => true];
        }

        return array_values($picks);
    }
}
