<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Player;
use App\Services\TelegramNotifier;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.admin')]
final class Broadcast extends Component
{
    #[Validate('required|string|min:2|max:3500')]
    public string $message = '';

    public string $audience = 'all'; // all | with_balance | recent_buyers

    public string $flash = '';

    private function recipients(): Collection
    {
        return match ($this->audience) {
            'with_balance' => Player::where('balance', '>', 0)->pluck('telegram_id'),
            'recent_buyers' => Player::where('total_tickets_bought', '>', 0)->pluck('telegram_id'),
            default => Player::pluck('telegram_id'),
        };
    }

    public function send(TelegramNotifier $notifier): void
    {
        $this->validate();

        $ids = $this->recipients();
        $notifier->broadcast($ids, $this->message, 'broadcast');

        $this->flash = 'Queued to '.$ids->count().' player(s). Delivery runs through the queue.';
        $this->reset('message');
    }

    public function render()
    {
        return view('livewire.admin.broadcast', [
            'counts' => [
                'all' => Player::count(),
                'with_balance' => Player::where('balance', '>', 0)->count(),
                'recent_buyers' => Player::where('total_tickets_bought', '>', 0)->count(),
            ],
        ]);
    }
}
