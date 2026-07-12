<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\DepositService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Review queue for manual (player-submitted) deposits. */
#[Layout('layouts.admin')]
final class Deposits extends Component
{
    use WithPagination;

    public string $filter = 'pending';

    /** Editable credit amount per pending tx id (pre-filled from the parsed SMS). */
    public array $amounts = [];

    public function approve(int $id, DepositService $service): void
    {
        $tx = Transaction::find($id);
        if ($tx === null) {
            return;
        }

        // Blank input = accept the amount parsed from the SMS.
        $amount = filled($this->amounts[$id] ?? null) ? (float) $this->amounts[$id] : (float) $tx->amount;

        try {
            $service->approveManual($tx, $amount);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        }

        $this->dispatch('toast', message: "Deposit #{$id} approved and credited.", type: 'success');
    }

    public function reject(int $id, DepositService $service): void
    {
        $tx = Transaction::find($id);
        if ($tx !== null) {
            $service->rejectManual($tx, 'Declined by admin');
            $this->dispatch('toast', message: "Deposit #{$id} rejected.", type: 'success');
        }
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Transaction::where('type', TransactionType::Deposit->value)
            ->where('meta->manual', true)
            ->with('player');

        if (in_array($this->filter, ['pending', 'completed', 'rejected'], true)) {
            $query->where('status', $this->filter);
        }

        return view('livewire.admin.deposits', [
            'deposits' => $query->latest('id')->paginate(15),
            'pendingCount' => Transaction::where('type', TransactionType::Deposit->value)
                ->where('status', TransactionStatus::Pending->value)->count(),
            'currency' => config('lottery.currency', 'ETB'),
        ]);
    }
}
