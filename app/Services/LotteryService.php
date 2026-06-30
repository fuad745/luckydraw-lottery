<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoundStatus;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Jobs\ProcessDraw;
use App\Jobs\StartNextRound;
use App\Models\Player;
use App\Models\Round;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LotteryService
{
    public function __construct(
        private readonly PlayerService $players,
        private readonly ReferralService $referrals,
        private readonly DrawService $draws,
        private readonly PrizeCalculator $prizes,
        private readonly TelegramNotifier $notifier,
        private readonly WalletService $wallet,
    ) {}

    public function createRound(string $title, int $totalTickets, float $ticketPrice, array $options = []): Round
    {
        $winners = max(1, (int) ($options['winners_count'] ?? 1));

        $round = Round::create([
            'title' => $title,
            'total_tickets' => $totalTickets,
            'ticket_price' => $ticketPrice,
            'currency' => $options['currency'] ?? config('lottery.currency', 'ETB'),
            'status' => RoundStatus::Open,
            'winners_count' => $winners,
            'prize_structure' => $options['prize_structure'] ?? PrizeCalculator::defaultStructure($winners),
            'allow_half_tickets' => $options['allow_half_tickets'] ?? true,
            'auto_draw' => $options['auto_draw'] ?? true,
            'auto_restart' => $options['auto_restart'] ?? false,
            'restart_delay_minutes' => $options['restart_delay_minutes'] ?? 5,
            'channel_id' => $options['channel_id'] ?? null,
            'draw_deadline' => $options['draw_deadline'] ?? null,
            'started_at' => now(),
        ]);

        $this->announceNewRound($round);

        return $round;
    }

    private function announceNewRound(Round $round): void
    {
        $msg = "🎉 <b>New round is live: {$round->title}!</b>\n".
            "🎟 {$round->total_tickets} tickets · {$round->ticket_price} {$round->currency} each\n".
            "🏆 {$round->winners_count} winner(s)\n".
            'Open the app and grab your lucky numbers! 🍀';

        $this->notifier->toChannel($round->channelId(), $msg, 'round_started', $round->id);

        if (config('lottery.announce_to_players')) {
            $this->notifier->broadcast(Player::pluck('telegram_id'), $msg, 'round_started', $round->id);
        }
    }

    /** Start a fresh round using a finished round's configuration. */
    public function cloneRound(Round $previous): Round
    {
        return $this->createRound(
            $this->nextTitle($previous->title),
            $previous->total_tickets,
            (float) $previous->ticket_price,
            [
                'currency' => $previous->currency,
                'winners_count' => $previous->winners_count,
                'prize_structure' => $previous->prize_structure,
                'allow_half_tickets' => $previous->allow_half_tickets,
                'auto_draw' => $previous->auto_draw,
                'auto_restart' => $previous->auto_restart,
                'restart_delay_minutes' => $previous->restart_delay_minutes,
                'channel_id' => $previous->channel_id,
            ],
        );
    }

    /**
     * Board state per number: 'free' | 'half_open' | 'taken', plus owner labels.
     *
     * @return array<int,array{state:string,label:?string,owner_id:?int,co_owner_id:?int}>
     */
    public function boardState(Round $round): array
    {
        $rows = $round->tickets()->get()->keyBy('ticket_number');
        $board = [];

        for ($n = 1; $n <= $round->total_tickets; $n++) {
            /** @var Ticket|null $t */
            $t = $rows->get($n);
            if ($t === null) {
                $board[$n] = ['state' => 'free', 'label' => null, 'owner_id' => null, 'co_owner_id' => null];
            } elseif ($t->hasOpenHalf()) {
                $board[$n] = ['state' => 'half_open', 'label' => $t->ownershipLabel(), 'owner_id' => $t->owner_telegram_id, 'co_owner_id' => null];
            } else {
                $board[$n] = ['state' => 'taken', 'label' => $t->ownershipLabel(), 'owner_id' => $t->owner_telegram_id, 'co_owner_id' => $t->co_owner_telegram_id];
            }
        }

        return $board;
    }

    /**
     * Buy the picked numbers (full and/or half) in one atomic, oversell-safe transaction.
     *
     * @return Collection<int, Ticket>
     *
     * @throws ValidationException
     */
    public function purchase(Round $round, PurchaseData $data): Collection
    {
        $picks = $data->picks;
        $maxPer = (int) config('lottery.max_tickets_per_purchase', 20);

        if ($picks === []) {
            throw ValidationException::withMessages(['picks' => 'Pick at least one number from the board.']);
        }
        if (count($picks) > $maxPer) {
            throw ValidationException::withMessages(['picks' => "You can pick at most {$maxPer} numbers at a time."]);
        }

        $buyer = $this->players->resolve(
            $data->buyerTelegramId,
            $data->buyerName,
            $data->buyerUsername,
            $data->buyerPhone,
            $data->referredByCode,
        );

        if ($buyer->isBanned()) {
            throw ValidationException::withMessages(['round' => 'Your account is suspended.']);
        }

        $previousTicketCount = (int) $buyer->total_tickets_bought;
        $cost = $this->purchaseCost($picks, (float) $round->ticket_price);

        try {
            /** @var array{tickets: Collection<int,Ticket>, filledHalves: array<int,Ticket>} $result */
            $result = DB::transaction(function () use ($round, $data, $buyer, $picks, $cost): array {
                $round = Round::whereKey($round->getKey())->lockForUpdate()->first();

                if (! $round || $round->status !== RoundStatus::Open) {
                    throw ValidationException::withMessages(['round' => 'This round is no longer open for purchases.']);
                }

                $rows = $round->tickets()->get()->keyBy('ticket_number');
                $created = collect();
                $filledHalves = [];

                foreach ($picks as $pick) {
                    $n = (int) $pick['number'];
                    $wantHalf = (bool) ($pick['half'] ?? false);

                    if ($n < 1 || $n > $round->total_tickets) {
                        throw ValidationException::withMessages(['picks' => "Ticket #{$n} is out of range."]);
                    }
                    if ($wantHalf && ! $round->allow_half_tickets) {
                        throw ValidationException::withMessages(['picks' => 'Half tickets are not allowed in this round.']);
                    }

                    /** @var Ticket|null $existing */
                    $existing = $rows->get($n);

                    if ($existing === null) {
                        // Brand-new number → full or first half.
                        $ticket = Ticket::create([
                            'round_id' => $round->id,
                            'ticket_number' => $n,
                            'owner_name' => $data->buyerName,
                            'owner_phone' => $data->buyerPhone ?? '',
                            'owner_telegram_id' => $data->buyerTelegramId,
                            'is_split' => $wantHalf,
                            'referred_by' => $buyer->referred_by,
                            'purchased_at' => now(),
                        ]);
                        $rows->put($n, $ticket);
                        $created->push($ticket);

                        continue;
                    }

                    // Existing number: only an open half can still be bought.
                    if (! $existing->hasOpenHalf()) {
                        throw ValidationException::withMessages(['picks' => "Ticket #{$n} is no longer available."]);
                    }
                    if ($existing->owner_telegram_id === $data->buyerTelegramId) {
                        throw ValidationException::withMessages(['picks' => "You already own a half of ticket #{$n}."]);
                    }

                    $existing->update([
                        'co_owner_name' => $data->buyerName,
                        'co_owner_phone' => $data->buyerPhone,
                        'co_owner_telegram_id' => $data->buyerTelegramId,
                    ]);
                    $created->push($existing);
                    $filledHalves[] = $existing;
                }

                $buyer->increment('total_tickets_bought', count($picks));

                // Pay for the tickets from the wallet (balance-checked, atomic).
                $numbers = $created->pluck('ticket_number')->sort()->map(fn ($n) => "#{$n}")->implode(', ');
                $this->wallet->debit($data->buyerTelegramId, $cost, TransactionType::Purchase, [
                    'round_id' => $round->id,
                    'note' => 'Tickets '.$numbers,
                ]);

                return ['tickets' => $created, 'filledHalves' => $filledHalves];
            });
        } catch (InsufficientBalanceException $e) {
            throw ValidationException::withMessages(['balance' => $e->getMessage()]);
        }

        $this->referrals->rewardOnFirstPurchase($buyer->refresh(), $previousTicketCount);

        $this->notifyPurchase($round, $result['tickets'], $data->buyerTelegramId);
        $this->notifyHalvesCompleted($round, $result['filledHalves'], $data->buyerName);

        $this->maybeTriggerDraw($round->refresh());

        return $result['tickets'];
    }

    /** Total cost of a set of board picks (full = 1.0, half = 0.5 of price). */
    private function purchaseCost(array $picks, float $price): float
    {
        $units = 0.0;
        foreach ($picks as $pick) {
            $units += ! empty($pick['half']) ? 0.5 : 1.0;
        }

        return round($units * $price, 2);
    }

    public function maybeTriggerDraw(Round $round): void
    {
        $shouldDraw = $round->status === RoundStatus::Open
            && (($round->auto_draw && $round->isFull()) || $round->deadlinePassed());

        if ($shouldDraw) {
            $this->startDraw($round);
        }
    }

    public function startDraw(Round $round): void
    {
        if ($round->status !== RoundStatus::Open) {
            return;
        }

        if ($round->ticketsSold() === 0) {
            $this->cancelRound($round, 'No tickets were sold.');

            return;
        }

        // Atomic compare-and-set on the Open→Drawing transition. Only one of any
        // concurrent triggers (a board-filling purchase racing the deadline cron)
        // wins this update, so exactly one ProcessDraw is ever dispatched.
        $claimed = Round::whereKey($round->getKey())
            ->where('status', RoundStatus::Open->value)
            ->update(['status' => RoundStatus::Drawing->value]);

        if ($claimed === 0) {
            return; // another trigger already started this draw
        }

        $round->setAttribute('status', RoundStatus::Drawing);

        $this->notifier->broadcast(
            $this->roundHolderIds($round),
            "🔔 <b>{$round->title}</b>\nSales are closed — the draw is starting NOW 🎰\nOpen the app to watch the balls roll!",
            'draw_starting',
            $round->id,
        );
        $this->notifier->toChannel(
            $round->channelId(),
            "🎰 <b>{$round->title}</b> — the draw is starting now! Winners announced in moments…",
            'draw_starting',
            $round->id,
        );

        $delay = (int) config('lottery.draw_suspense_seconds', 10);
        ProcessDraw::dispatch($round->id)->delay(now()->addSeconds($delay));
    }

    /** Reveal winners, distribute tiered prizes, announce, and maybe auto-restart. */
    public function performDraw(Round $round): Collection
    {
        if ($round->status !== RoundStatus::Drawing) {
            return $round->tickets()->where('is_winner', true)->orderBy('win_rank')->get();
        }

        $structure = $round->prizeStructure();
        $pot = $round->prizePool();
        $split = $this->prizes->distribute($pot, (float) $round->ticket_price, $structure);

        $outcome = DB::transaction(function () use ($round, $structure, $split, $pot): array {
            $round = Round::whereKey($round->getKey())->lockForUpdate()->first();

            // Re-assert the state under the row lock. If a concurrent ProcessDraw
            // already drew this round (or it was cancelled), bail without paying
            // out a second time — this is what prevents double-credited winnings.
            if (! $round || $round->status !== RoundStatus::Drawing) {
                return ['noop' => true];
            }

            $winners = $this->draws->pickWinners($round, count($structure));
            if ($winners->isEmpty()) {
                $round->update(['status' => RoundStatus::Cancelled, 'drawn_at' => now()]);

                return ['winners' => collect(), 'admin' => $pot, 'perHolder' => []];
            }

            $adminExtraCents = 0;
            $perHolderCents = []; // telegram_id => total cents, for DMs

            foreach ($structure as $i => $_tier) {
                $tierCents = Money::toCents($split['tiers'][$i] ?? 0.0);
                $ticket = $winners->get($i);

                if ($ticket === null) {
                    // Fewer winning tickets than tiers — unused prize goes to the house.
                    $adminExtraCents += $tierCents;

                    continue;
                }

                $ticket->update(['is_winner' => true, 'win_rank' => $i + 1, 'prize_amount' => Money::toAmount($tierCents)]);

                // Split the tier exactly across holders + an "unsold" house share, so
                // half-tickets never lose or leak a cent to rounding.
                $shares = $ticket->holderShares();        // tid => 0.5 | 1.0
                $tids = array_keys($shares);
                $weights = array_values($shares);
                $houseFraction = max(0.0, 1.0 - array_sum($shares));
                if ($houseFraction > 0) {
                    $weights[] = $houseFraction;          // last slot = house
                }

                $alloc = Money::allocate($tierCents, $weights);
                foreach ($tids as $j => $tid) {
                    $perHolderCents[$tid] = ($perHolderCents[$tid] ?? 0) + $alloc[$j];
                }
                if ($houseFraction > 0) {
                    $adminExtraCents += $alloc[count($tids)];
                }
            }

            $adminCut = Money::toAmount(Money::toCents($split['admin']) + $adminExtraCents);

            $round->update([
                'status' => RoundStatus::Closed,
                'winner_ticket_id' => $winners->first()->id,
                'admin_cut' => $adminCut,
                'drawn_at' => now(),
            ]);

            // Credit a win to every distinct holder of any winning ticket.
            $holderIds = $winners->flatMap(fn (Ticket $t) => array_keys($t->holderShares()))->unique()->values()->all();
            Player::whereIn('telegram_id', $holderIds)->increment('total_wins');

            // Credit each holder's actual payout to their lifetime stat AND wallet.
            $perHolder = [];
            foreach ($perHolderCents as $tid => $cents) {
                $amount = Money::toAmount($cents);
                $perHolder[$tid] = $amount;
                if ($cents > 0) {
                    Player::where('telegram_id', $tid)->increment('total_winnings', $amount);
                    $this->wallet->credit((int) $tid, $amount, TransactionType::Winning, [
                        'round_id' => $round->id,
                        'note' => 'Prize — '.$round->title,
                    ]);
                }
            }

            return ['winners' => $winners, 'admin' => $adminCut, 'perHolder' => $perHolder];
        });

        // A concurrent worker already finished this draw — return its result and
        // do nothing else (no second announcement, no duplicate restart).
        if ($outcome['noop'] ?? false) {
            return $round->fresh()?->tickets()->where('is_winner', true)->orderBy('win_rank')->get()
                ?? collect();
        }

        $this->announceResult($round->refresh(), $outcome['winners'], $outcome['perHolder']);
        $this->scheduleRestart($round);

        return $outcome['winners'];
    }

    public function cancelRound(Round $round, ?string $reason = null): void
    {
        if (! $round->status->isActive()) {
            return;
        }

        // Atomically claim the cancellation. If a draw is mid-payout it holds the
        // row lock, so this UPDATE blocks until it commits and then matches 0 rows
        // (status is Closed) — the round can never be both refunded AND paid out.
        $claimed = Round::whereKey($round->getKey())
            ->whereIn('status', [RoundStatus::Open->value, RoundStatus::Drawing->value])
            ->update(['status' => RoundStatus::Cancelled->value]);

        if ($claimed === 0) {
            return;
        }

        $round->setAttribute('status', RoundStatus::Cancelled);

        // Refund every wallet that paid into this round.
        $this->refundRound($round);

        $msg = "⚠️ <b>{$round->title}</b> has been cancelled.";
        if ($reason) {
            $msg .= "\n{$reason}";
        }
        $msg .= "\nAll ticket payments have been refunded to your wallet balance.";

        $this->notifier->broadcast($this->roundHolderIds($round), $msg, 'round_cancelled', $round->id);
        $this->notifier->toChannel($round->channelId(), $msg, 'round_cancelled', $round->id);
    }

    /** Credit every buyer back the amount they spent on a (now cancelled) round. */
    private function refundRound(Round $round): void
    {
        $spent = Transaction::where('round_id', $round->id)
            ->where('type', TransactionType::Purchase->value)
            ->selectRaw('telegram_id, SUM(amount) as total')
            ->groupBy('telegram_id')
            ->get();

        foreach ($spent as $row) {
            if ((float) $row->total > 0) {
                $this->wallet->credit((int) $row->telegram_id, (float) $row->total, TransactionType::Refund, [
                    'round_id' => $round->id,
                    'note' => 'Refund — '.$round->title.' cancelled',
                ]);
            }
        }
    }

    private function scheduleRestart(Round $round): void
    {
        if (! $round->auto_restart) {
            return;
        }

        $delay = max(1, (int) $round->restart_delay_minutes);
        StartNextRound::dispatch($round->id)->delay(now()->addMinutes($delay));
    }

    /** "Friday Draw #3" -> "Friday Draw #4"; otherwise append " #2". */
    private function nextTitle(string $title): string
    {
        if (preg_match('/^(.*?)#(\d+)\s*$/', $title, $m)) {
            return rtrim($m[1]).' #'.((int) $m[2] + 1);
        }

        return $title.' #2';
    }

    private function roundHolderIds(Round $round): array
    {
        $owners = $round->tickets()->pluck('owner_telegram_id');
        $coOwners = $round->tickets()->whereNotNull('co_owner_telegram_id')->pluck('co_owner_telegram_id');

        return $owners->merge($coOwners)->filter()->unique()->values()->all();
    }

    private function notifyPurchase(Round $round, Collection $tickets, int $buyerId): void
    {
        $parts = [];
        foreach ($tickets as $t) {
            $isHalf = $t->is_split && (
                ($t->owner_telegram_id === $buyerId && $t->co_owner_telegram_id === null)
                || $t->co_owner_telegram_id === $buyerId
            );
            $parts[] = ($isHalf ? '½ ' : '').'#'.$t->ticket_number;
        }

        $list = implode(', ', $parts);
        $units = number_format(array_reduce($tickets->all(), fn ($c, $t) => $c + (($t->is_split && ($t->co_owner_telegram_id === $buyerId || $t->co_owner_telegram_id === null)) ? 0.5 : 1), 0), 1);

        $this->notifier->send(
            $buyerId,
            "✅ <b>Purchase confirmed!</b>\nYou got <b>{$list}</b> in <b>{$round->title}</b>.\n\n🎟 Stake: {$units} ticket(s)\n💰 Prize pool now: {$round->prizePool()} {$round->currency}\n\nGood luck! We'll DM you the moment you win.",
            'ticket_purchased',
            $round->id,
        );
    }

    /** Tell the original half-owner that their number is now fully shared. */
    private function notifyHalvesCompleted(Round $round, array $filledHalves, string $buyerName): void
    {
        foreach ($filledHalves as $t) {
            $this->notifier->send(
                (int) $t->owner_telegram_id,
                "🤝 <b>Your ticket #{$t->ticket_number} is now fully shared!</b>\n{$buyerName} bought the other half in <b>{$round->title}</b>. If it wins, you each take 50% of that ticket's prize.",
                'half_completed',
                $round->id,
            );
        }
    }

    private function announceResult(Round $round, Collection $winners, array $perHolder): void
    {
        if ($winners->isEmpty()) {
            $this->notifier->toChannel($round->channelId(), "⚠️ <b>{$round->title}</b> ended with no valid tickets.", 'draw_void', $round->id);

            return;
        }

        // 1) Personal DM to each winning holder.
        foreach ($perHolder as $tid => $amount) {
            $amount = round($amount, 2);
            if ($amount <= 0) {
                continue;
            }
            $rank = $this->holderBestRank($winners, (int) $tid);
            $medal = ['1' => '🥇', '2' => '🥈', '3' => '🥉'][(string) $rank] ?? '🏅';
            $this->notifier->send(
                (int) $tid,
                "🎉 <b>YOU WON!</b> {$medal}\nYou placed <b>#{$rank}</b> in <b>{$round->title}</b>.\n💰 Your prize: <b>{$amount} {$round->currency}</b>\n\nThe organiser will arrange your payout. Congratulations! 🥳",
                'win',
                $round->id,
            );
        }

        // 2) Public results to the channel.
        $lines = $winners->map(function (Ticket $t) use ($round) {
            $medal = ['1' => '🥇', '2' => '🥈', '3' => '🥉'][(string) $t->win_rank] ?? '🏅';
            $who = $t->is_split ? $t->ownershipLabel() : $t->owner_name;

            return "{$medal} #{$t->win_rank} — Ticket <b>#{$t->ticket_number}</b> ({$who}) → <b>{$t->prize_amount} {$round->currency}</b>";
        })->implode("\n");

        $this->notifier->toChannel(
            $round->channelId(),
            "🎰🏆 <b>{$round->title} — RESULTS</b>\n\n{$lines}\n\n💰 Prize pool: {$round->prizePool()} {$round->currency}\nCongratulations to the winners! 🎉",
            'draw_result',
            $round->id,
        );
    }

    private function holderBestRank(Collection $winners, int $telegramId): int
    {
        $best = PHP_INT_MAX;
        foreach ($winners as $t) {
            if (array_key_exists($telegramId, $t->holderShares())) {
                $best = min($best, (int) $t->win_rank);
            }
        }

        return $best === PHP_INT_MAX ? 0 : $best;
    }
}
