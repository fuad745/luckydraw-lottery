<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_id',
        'type',
        'status',
        'amount',
        'balance_after',
        'provider',
        'reference',
        'round_id',
        'note',
        'meta',
        'processed_at',
    ];

    protected $casts = [
        'telegram_id' => 'string',
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
        'processed_at' => 'datetime',
    ];

    public function setTelegramIdAttribute($value): void
    {
        $this->attributes['telegram_id'] = $value !== null ? (string) $value : null;
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'telegram_id', 'telegram_id');
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /** Signed amount for display (+deposit / −withdrawal). */
    public function signedAmount(): float
    {
        return ($this->type->isCredit() ? 1 : -1) * (float) $this->amount;
    }
}
