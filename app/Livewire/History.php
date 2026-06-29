<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\RoundStatus;
use App\Models\Round;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
final class History extends Component
{
    use WithPagination;

    public function render()
    {
        $rounds = Round::whereIn('status', [RoundStatus::Closed->value, RoundStatus::Cancelled->value])
            ->with('winners')
            ->latest('id')
            ->paginate(10);

        return view('livewire.history', ['rounds' => $rounds]);
    }
}
