<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Player extends Model
{
    use HasFactory;

    /** Telegram id is the primary key (non-incrementing). */
    protected $primaryKey = 'telegram_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'telegram_id',
        'name',
        'username',
        'locale',
        'phone',
        'referral_code',
        'referred_by',
        'referral_rewarded_at',
        'referral_count',
        'free_tickets',
        'total_tickets_bought',
        'total_wins',
        'total_winnings',
        'balance',
        'banned_at',
    ];

    protected $casts = [
        'telegram_id' => 'string',
        'referral_count' => 'integer',
        'free_tickets' => 'integer',
        'total_tickets_bought' => 'integer',
        'total_wins' => 'integer',
        'total_winnings' => 'decimal:2',
        'balance' => 'decimal:2',
        'referral_rewarded_at' => 'datetime',
        'banned_at' => 'datetime',
    ];

    /** In-memory defaults so freshly created models never expose null counters. */
    protected $attributes = [
        'referral_count' => 0,
        'free_tickets' => 0,
        'total_tickets_bought' => 0,
        'total_wins' => 0,
        'total_winnings' => 0,
        'balance' => 0,
    ];

    public function setTelegramIdAttribute($value): void
    {
        $this->attributes['telegram_id'] = $value !== null ? (string) $value : null;
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'owner_telegram_id', 'telegram_id');
    }

    /** Players this player referred. */
    public function referrals(): HasMany
    {
        return $this->hasMany(Player::class, 'referred_by', 'referral_code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'telegram_id', 'telegram_id');
    }

    public function referralLink(string $botUsername): string
    {
        return "https://t.me/{$botUsername}?start=ref_{$this->referral_code}";
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }
}
