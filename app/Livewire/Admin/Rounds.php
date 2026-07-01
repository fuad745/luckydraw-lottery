<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TransactionType;
use App\Models\Round;
use App\Models\Transaction;
use App\Services\LotteryService;
use App\Services\PrizeCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin')]
final class Rounds extends Component
{
    #[Validate('required|string|min:3|max:80')]
    public string $title = '';

    // Numeric inputs are nullable (so clearing the field hydrates to null
    // instead of throwing a 500 on a typed int) and default to null (so an empty
    // submit stays null and trips `required`, rather than snapping back to a
    // non-null PHP default). The real defaults are seeded in mount().
    #[Validate('required|integer|min:2|max:1000')]
    public ?int $totalTickets = null;

    #[Validate('required|numeric|min:1')]
    public ?float $ticketPrice = null;

    #[Validate('required|string|max:8')]
    public string $currency = 'ETB';

    #[Validate('required|integer|min:1|max:5')]
    public ?int $winnersCount = 1;

    /** @var array<int,array{type:string,value:float}> */
    public array $tiers = [];

    public bool $allowHalf = true;

    public bool $autoDraw = true;

    public bool $autoRestart = false;

    #[Validate('nullable|integer|min:1|max:1440')]
    public ?int $restartDelay = 5;

    public string $channelId = '';

    public string $deadline = '';

    public string $flash = '';

    public function mount(): void
    {
        $this->totalTickets = 50;
        $this->ticketPrice = 50;
        $this->currency = (string) config('lottery.currency', 'ETB');
        $this->channelId = (string) config('lottery.channel_id', '');
        $this->syncTiers();
    }

    public function updatedWinnersCount(): void
    {
        $this->winnersCount = max(1, min(5, (int) $this->winnersCount));
        $this->syncTiers();
    }

    private function syncTiers(): void
    {
        $count = max(1, min(5, (int) $this->winnersCount));
        $defaults = PrizeCalculator::defaultStructure($count);
        $tiers = [];
        for ($i = 0; $i < $count; $i++) {
            $existing = $this->tiers[$i] ?? null;
            $default = $defaults[$i] ?? ['type' => 'percent', 'value' => 0];
            $tiers[] = [
                'type' => $existing['type'] ?? $default['type'],
                'value' => (float) ($existing['value'] ?? ($default['value'] ?? 0)),
            ];
        }
        $this->tiers = $tiers;
    }

    /** @return array<int,array<string,mixed>> */
    private function prizeStructure(): array
    {
        return array_map(function (array $tier): array {
            if (($tier['type'] ?? 'percent') === 'ticket_price') {
                return ['type' => 'ticket_price'];
            }

            return ['type' => 'percent', 'value' => (float) ($tier['value'] ?? 0)];
        }, $this->tiers);
    }

    public function createRound(LotteryService $lottery): void
    {
        $this->validate();

        $lottery->createRound($this->title, $this->totalTickets, $this->ticketPrice, [
            'currency' => $this->currency,
            'winners_count' => $this->winnersCount,
            'prize_structure' => $this->prizeStructure(),
            'allow_half_tickets' => $this->allowHalf,
            'auto_draw' => $this->autoDraw,
            'auto_restart' => $this->autoRestart,
            'restart_delay_minutes' => $this->restartDelay,
            'channel_id' => $this->channelId !== '' ? $this->channelId : null,
            'draw_deadline' => $this->deadline !== '' ? Carbon::parse($this->deadline) : null,
        ]);

        $this->reset('title', 'deadline');
        $this->flash = 'New round started!';
    }

    public function startDrawNow(LotteryService $lottery): void
    {
        $round = Round::current();
        if ($round !== null) {
            $lottery->startDraw($round);
            $this->flash = 'Draw triggered for '.$round->title.'.';
        }
    }

    public function cancelRound(LotteryService $lottery): void
    {
        $round = Round::current();
        if ($round !== null) {
            $lottery->cancelRound($round, 'Cancelled by the organiser.');
            $this->flash = $round->title.' was cancelled and buyers refunded.';
        }
    }

    public function render(PrizeCalculator $calculator)
    {
        // Cast defensively — any of these may be transiently null while the
        // operator is mid-edit (a cleared input).
        $price = (float) $this->ticketPrice;
        $pot = round(((int) $this->totalTickets) * $price, 2);
        $preview = $calculator->distribute($pot, $price, $this->prizeStructure());

        $recent = Round::latest('id')->limit(12)->get();

        return view('livewire.admin.rounds', [
            'current' => Round::current(),
            'recent' => $recent,
            'pnl' => $this->profitAndLoss($recent),
            'previewPot' => $pot,
            'previewTiers' => $preview['tiers'],
            'previewAdmin' => $preview['admin'],
        ]);
    }

    /**
     * Per-round reconciliation: sales in, prizes + refunds out, house cut, and a
     * balance check (sales − prizes − refunds should equal the house cut).
     *
     * @param  Collection<int,Round>  $rounds
     * @return array<int,array{sales:float,prizes:float,refunds:float,house:float,balanced:bool}>
     */
    private function profitAndLoss($rounds): array
    {
        $byRound = Transaction::whereIn('round_id', $rounds->pluck('id'))
            ->selectRaw('round_id, type, SUM(amount) as total')
            ->groupBy('round_id', 'type')
            ->get()
            ->groupBy('round_id');

        $pnl = [];
        foreach ($rounds as $round) {
            $rows = $byRound->get($round->id) ?? collect();
            $sum = fn (TransactionType $t): float => (float) ($rows->firstWhere('type', $t->value)->total ?? 0);

            $sales = $sum(TransactionType::Purchase);
            $prizes = $sum(TransactionType::Winning);
            $refunds = $sum(TransactionType::Refund);
            $house = (float) $round->admin_cut;

            $pnl[$round->id] = [
                'sales' => $sales,
                'prizes' => $prizes,
                'refunds' => $refunds,
                'house' => $house,
                // Within a cent = reconciled (the integer-cents math should make it exact).
                'balanced' => abs($sales - $prizes - $refunds - $house) < 0.01,
            ];
        }

        return $pnl;
    }
}
