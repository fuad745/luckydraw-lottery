<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Round;
use App\Services\LotteryService;
use App\Services\PrizeCalculator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin')]
final class Rounds extends Component
{
    #[Validate('required|string|min:3|max:80')]
    public string $title = '';

    #[Validate('required|integer|min:2|max:1000')]
    public int $totalTickets = 50;

    #[Validate('required|numeric|min:1')]
    public float $ticketPrice = 50;

    #[Validate('required|string|max:8')]
    public string $currency = 'ETB';

    #[Validate('required|integer|min:1|max:5')]
    public int $winnersCount = 1;

    /** @var array<int,array{type:string,value:float}> */
    public array $tiers = [];

    public bool $allowHalf = true;

    public bool $autoDraw = true;

    public bool $autoRestart = false;

    #[Validate('integer|min:1|max:1440')]
    public int $restartDelay = 5;

    public string $channelId = '';

    public string $deadline = '';

    public string $flash = '';

    public function mount(): void
    {
        $this->currency = (string) config('lottery.currency', 'ETB');
        $this->channelId = (string) config('lottery.channel_id', '');
        $this->syncTiers();
    }

    public function updatedWinnersCount(): void
    {
        $this->winnersCount = max(1, min(5, $this->winnersCount));
        $this->syncTiers();
    }

    private function syncTiers(): void
    {
        $defaults = PrizeCalculator::defaultStructure($this->winnersCount);
        $tiers = [];
        for ($i = 0; $i < $this->winnersCount; $i++) {
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
        $pot = round($this->totalTickets * $this->ticketPrice, 2);
        $preview = $calculator->distribute($pot, $this->ticketPrice, $this->prizeStructure());

        return view('livewire.admin.rounds', [
            'current' => Round::current(),
            'recent' => Round::latest('id')->limit(12)->get(),
            'previewPot' => $pot,
            'previewTiers' => $preview['tiers'],
            'previewAdmin' => $preview['admin'],
        ]);
    }
}
