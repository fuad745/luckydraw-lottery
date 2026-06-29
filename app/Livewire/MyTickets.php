<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Ticket;
use App\Telegram\TelegramAuth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class MyTickets extends Component
{
    public function render()
    {
        $auth = app(TelegramAuth::class);
        $player = $auth->player();
        $id = $auth->id();

        $tickets = collect();
        if ($player !== null) {
            // Tickets you own fully OR hold a half of.
            $tickets = Ticket::query()
                ->where('owner_telegram_id', $id)
                ->orWhere('co_owner_telegram_id', $id)
                ->with('round')
                ->latest('id')
                ->get()
                ->groupBy(fn ($t) => $t->round->title);
        }

        return view('livewire.my-tickets', [
            'auth' => $auth,
            'player' => $player,
            'myId' => $id,
            'grouped' => $tickets,
        ]);
    }
}
