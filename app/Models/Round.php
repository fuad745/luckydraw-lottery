<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoundStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Round extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'total_tickets',
        'ticket_price',
        'currency',
        'status',
        'winners_count',
        'prize_structure',
        'allow_half_tickets',
        'auto_draw',
        'auto_restart',
        'restart_delay_minutes',
        'channel_id',
        'admin_cut',
        'draw_deadline',
        'winner_ticket_id',
        'started_at',
        'drawn_at',
    ];

    protected $casts = [
        'total_tickets' => 'integer',
        'ticket_price' => 'decimal:2',
        'status' => RoundStatus::class,
        'winners_count' => 'integer',
        'prize_structure' => 'array',
        'allow_half_tickets' => 'boolean',
        'auto_draw' => 'boolean',
        'auto_restart' => 'boolean',
        'restart_delay_minutes' => 'integer',
        'admin_cut' => 'decimal:2',
        'draw_deadline' => 'datetime',
        'started_at' => 'datetime',
        'drawn_at' => 'datetime',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function winnerTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'winner_ticket_id');
    }

    /** All winning tickets, ordered by placement. */
    public function winners(): HasMany
    {
        return $this->hasMany(Ticket::class)->where('is_winner', true)->orderBy('win_rank');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [RoundStatus::Open->value, RoundStatus::Drawing->value]);
    }

    /** The single round currently accepting/awaiting a draw, if any. */
    public static function current(): ?self
    {
        return self::active()->latest('id')->first();
    }

    /** Count of ticket rows (each row = one number, full or split). */
    public function ticketsSold(): int
    {
        return $this->tickets()->count();
    }

    /**
     * Fractional "units" sold across the board: a full ticket = 1.0, each
     * filled half = 0.5. This drives the prize pool and "sold out" logic.
     */
    public function soldUnits(): float
    {
        $row = $this->tickets()
            ->selectRaw('
                SUM(CASE WHEN is_split = 0 THEN 1
                         ELSE 0.5 * (1 + CASE WHEN co_owner_telegram_id IS NULL THEN 0 ELSE 1 END)
                    END) as units')
            ->first();

        return (float) ($row->units ?? 0);
    }

    public function unitsRemaining(): float
    {
        return max(0.0, $this->total_tickets - $this->soldUnits());
    }

    public function ticketsRemaining(): int
    {
        return (int) ceil($this->unitsRemaining());
    }

    /** Full when every number's capacity (1.0 each) is taken. */
    public function isFull(): bool
    {
        return $this->soldUnits() >= $this->total_tickets - 0.001;
    }

    public function isOpen(): bool
    {
        return $this->status === RoundStatus::Open;
    }

    /** Live prize pool = units sold × ticket price (halves count as 0.5). */
    public function prizePool(): float
    {
        return round($this->soldUnits() * (float) $this->ticket_price, 2);
    }

    /** Resolved prize tiers; falls back to a single 100% winner. */
    public function prizeStructure(): array
    {
        $structure = $this->prize_structure;

        if (is_array($structure) && $structure !== []) {
            return $structure;
        }

        return [['type' => 'percent', 'value' => 100]];
    }

    /** Channel to post results to (per-round override or global default). */
    public function channelId(): ?string
    {
        return $this->channel_id ?: config('lottery.channel_id');
    }

    public function deadlinePassed(): bool
    {
        return $this->draw_deadline !== null && $this->draw_deadline->isPast();
    }
}
