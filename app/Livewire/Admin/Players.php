<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Player;
use App\Services\WalletService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
final class Players extends Component
{
    use WithPagination;

    public string $search = '';

    // Balance-adjustment modal
    public ?int $editing = null;

    // Nullable so clearing the field hydrates to null (a clean "required"
    // validation error) instead of throwing a 500 on a typed float.
    public ?float $adjustAmount = null;

    public string $adjustNote = '';

    public string $flash = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openAdjust(int $telegramId): void
    {
        $this->editing = $telegramId;
        $this->adjustAmount = null;
        $this->adjustNote = '';
    }

    public function saveAdjust(WalletService $wallet): void
    {
        $this->validate([
            'adjustAmount' => 'required|numeric|not_in:0',
            'adjustNote' => 'nullable|string|max:120',
        ]);

        $player = Player::find($this->editing);
        if ($player === null) {
            $this->editing = null;

            return;
        }

        $note = 'Admin adjustment'.($this->adjustNote !== '' ? ': '.$this->adjustNote : '');

        try {
            if ($this->adjustAmount > 0) {
                $wallet->credit($player->telegram_id, $this->adjustAmount, TransactionType::Adjustment, ['note' => $note]);
            } else {
                $wallet->debit($player->telegram_id, abs($this->adjustAmount), TransactionType::Adjustment, ['note' => $note]);
            }
        } catch (InsufficientBalanceException $e) {
            $this->addError('adjustAmount', $e->getMessage());

            return;
        }

        $this->flash = "Adjusted {$player->name}'s balance.";
        $this->editing = null;
    }

    public function toggleBan(int $telegramId): void
    {
        $player = Player::find($telegramId);
        if ($player !== null) {
            $player->update(['banned_at' => $player->isBanned() ? null : now()]);
            $this->flash = $player->isBanned() ? "{$player->name} banned." : "{$player->name} unbanned.";
        }
    }

    public function render()
    {
        $players = Player::query()
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where('name', 'like', $term)
                    ->orWhere('username', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('telegram_id', 'like', $term);
            })
            ->orderByDesc('balance')
            ->paginate(20);

        return view('livewire.admin.players', [
            'players' => $players,
            'currency' => config('lottery.currency', 'ETB'),
            'editingPlayer' => $this->editing ? Player::find($this->editing) : null,
        ]);
    }
}
