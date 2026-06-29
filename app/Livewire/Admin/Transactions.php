<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
final class Transactions extends Component
{
    use WithPagination;

    #[Url]
    public string $type = 'all';

    #[Url]
    public string $search = '';

    public function updating($name): void
    {
        if (in_array($name, ['type', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $query = Transaction::query()->with('player');

        if ($this->type !== 'all') {
            $query->where('type', $this->type);
        }
        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('telegram_id', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhereHas('player', fn ($p) => $p->where('name', 'like', $term));
            });
        }

        return view('livewire.admin.transactions', [
            'transactions' => $query->latest('id')->paginate(25),
            'types' => ['all', 'deposit', 'withdrawal', 'purchase', 'winning', 'refund', 'adjustment'],
            'currency' => config('lottery.currency', 'ETB'),
        ]);
    }
}
