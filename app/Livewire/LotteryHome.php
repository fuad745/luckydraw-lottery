<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\RoundStatus;
use App\Models\Round;
use App\Services\LotteryService;
use App\Services\PurchaseData;
use App\Telegram\TelegramAuth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class LotteryHome extends Component
{
    /** Referral code captured from the deep link (?ref=CODE). */
    #[Url(as: 'ref')]
    public string $ref = '';

    /** Board state, entangled into Alpine for instant client-side selection. */
    public array $board = [];

    private function auth(): TelegramAuth
    {
        return app(TelegramAuth::class);
    }

    private function featuredRound(): ?Round
    {
        return Round::current()
            ?? Round::whereIn('status', [RoundStatus::Closed->value])
                ->latest('drawn_at')
                ->first();
    }

    /**
     * Purchase the numbers selected on the board.
     *
     * @param  array<int>  $full
     * @param  array<int>  $half
     */
    public function buy(LotteryService $lottery, array $full = [], array $half = []): void
    {
        $auth = $this->auth();
        if (! $auth->check()) {
            $this->dispatch('toast', message: 'Open this app from Telegram to buy.');

            return;
        }

        $player = $auth->player();

        $round = Round::current();
        if ($round === null || ! $round->isOpen()) {
            $this->dispatch('toast', message: 'There is no open round right now.');

            return;
        }

        $picks = PurchaseData::picksFromBoard($full, $half);

        try {
            $tickets = $lottery->purchase($round, new PurchaseData(
                buyerTelegramId: $auth->id(),
                buyerName: $player->name,
                buyerPhone: $player->phone,
                buyerUsername: $auth->username(),
                picks: $picks,
                referredByCode: $this->ref ?: null,
            ));
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first());

            return;
        }

        $list = $tickets->pluck('ticket_number')->sort()->values()
            ->map(fn ($n) => "#{$n}")->implode(', ');

        $this->dispatch('haptic', type: 'notification', style: 'success');
        $this->dispatch('cleared'); // tell Alpine to reset the selection
        $this->dispatch('toast', message: "You got {$list}! 🎟");
        $this->dispatch('purchased',
            numbers: $list,
            shareUrl: $player->referralLink((string) config('lottery.bot_username')) ?: (string) config('lottery.mini_app_url'),
            title: $round->title,
        );
    }

    public function render()
    {
        $round = $this->featuredRound();
        $auth = $this->auth();
        $me = $auth->id();

        $this->board = [];
        $soldUnits = 0.0;

        if ($round !== null) {
            $rows = $round->tickets()->get()->keyBy('ticket_number');

            for ($n = 1; $n <= $round->total_tickets; $n++) {
                $t = $rows->get($n);
                if ($t === null) {
                    $this->board[] = ['n' => $n, 's' => 'free', 'mine' => false, 'who' => null];

                    continue;
                }

                $soldUnits += $t->fractionSold();
                $mine = $me !== null && in_array($me, $t->holderTelegramIds(), true);

                if ($t->hasOpenHalf()) {
                    $this->board[] = ['n' => $n, 's' => 'half', 'mine' => $mine, 'who' => $t->ownershipLabel()];
                } else {
                    $this->board[] = ['n' => $n, 's' => 'taken', 'mine' => $mine, 'who' => $t->ownershipLabel()];
                }
            }
        }

        return view('livewire.lottery-home', [
            'round' => $round,
            'auth' => $auth,
            'player' => $auth->player(),
            'soldUnits' => $soldUnits,
            'prizePool' => $round ? round($soldUnits * (float) $round->ticket_price, 2) : 0,
            'remaining' => $round ? (int) ceil(max(0, $round->total_tickets - $soldUnits)) : 0,
            'winners' => $round && $round->status === RoundStatus::Closed
                ? $round->tickets()->where('is_winner', true)->orderBy('win_rank')->get()
                : collect(),
        ]);
    }
}
