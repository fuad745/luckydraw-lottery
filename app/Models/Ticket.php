<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id',
        'ticket_number',
        'owner_name',
        'owner_phone',
        'owner_telegram_id',
        'is_split',
        'co_owner_name',
        'co_owner_phone',
        'co_owner_telegram_id',
        'is_winner',
        'win_rank',
        'prize_amount',
        'referred_by',
        'purchased_at',
    ];

    protected $casts = [
        'ticket_number' => 'integer',
        'owner_telegram_id' => 'integer',
        'co_owner_telegram_id' => 'integer',
        'is_split' => 'boolean',
        'is_winner' => 'boolean',
        'win_rank' => 'integer',
        'prize_amount' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'owner_telegram_id', 'telegram_id');
    }

    public function coOwner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'co_owner_telegram_id', 'telegram_id');
    }

    /** Telegram ids of everyone who owns a stake in this ticket. */
    public function holderTelegramIds(): array
    {
        return array_values(array_filter([
            $this->owner_telegram_id,
            $this->is_split ? $this->co_owner_telegram_id : null,
        ]));
    }

    /** Fraction of the number this ticket has actually sold (0.5, 1.0). */
    public function fractionSold(): float
    {
        if (! $this->is_split) {
            return 1.0;
        }

        return $this->co_owner_telegram_id !== null ? 1.0 : 0.5;
    }

    /** True when this is a half-ticket with its second half still available. */
    public function hasOpenHalf(): bool
    {
        return $this->is_split && $this->co_owner_telegram_id === null;
    }

    /**
     * Map of telegram_id => fraction owned (each half-holder owns 0.5).
     * Unsold halves are simply absent (their share goes to the house).
     *
     * @return array<int,float>
     */
    public function holderShares(): array
    {
        if (! $this->is_split) {
            return [$this->owner_telegram_id => 1.0];
        }

        $shares = [$this->owner_telegram_id => 0.5];
        if ($this->co_owner_telegram_id !== null) {
            $shares[$this->co_owner_telegram_id] = 0.5;
        }

        return $shares;
    }

    /** Display label for who owns this number (handles half ownership). */
    public function ownershipLabel(): string
    {
        if (! $this->is_split) {
            return $this->owner_name;
        }

        $second = $this->co_owner_name ?? 'open ½';

        return "½ {$this->owner_name} · ½ {$second}";
    }
}
