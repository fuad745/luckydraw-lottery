<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationLog extends Model
{
    use Prunable;

    protected $table = 'notifications_log';

    protected $fillable = [
        'telegram_id',
        'type',
        'message',
        'round_id',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'telegram_id' => 'string',
        'sent_at' => 'datetime',
    ];

    public function setTelegramIdAttribute($value): void
    {
        $this->attributes['telegram_id'] = $value !== null ? (string) $value : null;
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /** Delivery logs are transient — drop them after the retention window. */
    public function prunable(): Builder
    {
        $days = max(1, (int) config('lottery.notifications_retention_days', 30));

        return self::where('sent_at', '<', now()->subDays($days));
    }
}
