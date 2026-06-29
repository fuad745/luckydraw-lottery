<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Player;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Leaderboard extends Component
{
    public function render()
    {
        $winners = Player::where('total_wins', '>', 0)
            ->orderByDesc('total_winnings')
            ->orderByDesc('total_wins')
            ->limit(50)
            ->get();

        $currency = config('lottery.currency', 'ETB');

        return view('livewire.leaderboard', ['winners' => $winners, 'currency' => $currency]);
    }
}
