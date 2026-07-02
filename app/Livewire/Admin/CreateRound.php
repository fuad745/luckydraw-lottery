<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\LotteryService;
use App\Services\PrizeCalculator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin')]
final class CreateRound extends Component
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

        $round = $lottery->createRound($this->title, $this->totalTickets, $this->ticketPrice, [
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

        // Post-redirect toast (picked up by the layout's toast host).
        session()->flash('admin_toast', [
            'message' => '"'.$round->title.'" is live — players can buy tickets now.',
            'type' => 'success',
        ]);

        $this->redirectRoute('admin.rounds');
    }

    public function render(PrizeCalculator $calculator)
    {
        // Cast defensively — any of these may be transiently null while the
        // operator is mid-edit (a cleared input).
        $price = (float) $this->ticketPrice;
        $pot = round(((int) $this->totalTickets) * $price, 2);
        $preview = $calculator->distribute($pot, $price, $this->prizeStructure());

        return view('livewire.admin.create-round', [
            'previewPot' => $pot,
            'previewTiers' => $preview['tiers'],
            'previewAdmin' => $preview['admin'],
        ]);
    }
}
