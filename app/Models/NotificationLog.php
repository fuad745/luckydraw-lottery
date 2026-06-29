<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationLog extends Model
{
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
        'telegram_id' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
