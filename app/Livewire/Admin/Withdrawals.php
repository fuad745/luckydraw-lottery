<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\WithdrawalService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
final class Withdrawals extends Component
{
    use WithPagination;

    public string $filter = 'pending';

    public function approve(int $id, WithdrawalService $service): void
    {
        $tx = Transaction::find($id);
        if ($tx !== null) {
            $service->approve($tx);
            $this->dispatch('toast', message: "Withdrawal #{$id} marked as paid.", type: 'success');
        }
    }

    public function reject(int $id, WithdrawalService $service): void
    {
        $tx = Transaction::find($id);
        if ($tx !== null) {
            $service->reject($tx, 'Declined by admin');
            $this->dispatch('toast', message: "Withdrawal #{$id} refunded.", type: 'success');
        }
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Transaction::where('type', TransactionType::Withdrawal->value)->with('player');

        if (in_array($this->filter, ['pending', 'completed', 'rejected'], true)) {
            $query->where('status', $this->filter);
        }

        return view('livewire.admin.withdrawals', [
            'withdrawals' => $query->latest('id')->paginate(15),
            'pendingCount' => Transaction::where('type', TransactionType::Withdrawal->value)->where('status', TransactionStatus::Pending->value)->count(),
            'currency' => config('lottery.currency', 'ETB'),
        ]);
    }
}
