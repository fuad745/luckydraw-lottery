<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TransactionType;
use App\Models\Round;
use App\Models\Transaction;
use App\Services\LotteryService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
final class Rounds extends Component
{
    public function startDrawNow(LotteryService $lottery): void
    {
        $round = Round::current();
        if ($round !== null) {
            $lottery->startDraw($round);
            $this->dispatch('toast', message: 'Draw triggered for '.$round->title.'.', type: 'success');
        }
    }

    public function cancelRound(LotteryService $lottery): void
    {
        $round = Round::current();
        if ($round !== null) {
            $lottery->cancelRound($round, 'Cancelled by the organiser.');
            $this->dispatch('toast', message: $round->title.' was cancelled and buyers refunded.', type: 'success');
        }
    }

    /** Delete a round that has no players/payments (guarded in the service). */
    public function deleteRound(LotteryService $lottery, int $roundId): void
    {
        $round = Round::find($roundId);
        if ($round === null) {
            return;
        }

        try {
            $lottery->deleteRound($round);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        }

        $this->dispatch('toast', message: '"'.$round->title.'" deleted.', type: 'success');
    }

    public function render()
    {
        // tickets_count powers the "deletable?" check (no tickets = no players).
        $recent = Round::withCount('tickets')->latest('id')->limit(12)->get();

        return view('livewire.admin.rounds', [
            'current' => Round::current(),
            'recent' => $recent,
            'pnl' => $this->profitAndLoss($recent),
        ]);
    }

    /**
     * Per-round reconciliation: sales in, prizes + refunds out, house cut, and a
     * balance check (sales − prizes − refunds should equal the house cut).
     *
     * @param  Collection<int,Round>  $rounds
     * @return array<int,array{sales:float,prizes:float,refunds:float,house:float,balanced:bool}>
     */
    private function profitAndLoss($rounds): array
    {
        $byRound = Transaction::whereIn('round_id', $rounds->pluck('id'))
            ->selectRaw('round_id, type, SUM(amount) as total')
            ->groupBy('round_id', 'type')
            ->get()
            ->groupBy('round_id');

        $pnl = [];
        foreach ($rounds as $round) {
            $rows = $byRound->get($round->id) ?? collect();
            $sum = fn (TransactionType $t): float => (float) ($rows->firstWhere('type', $t->value)->total ?? 0);

            $sales = $sum(TransactionType::Purchase);
            $prizes = $sum(TransactionType::Winning);
            $refunds = $sum(TransactionType::Refund);
            $house = (float) $round->admin_cut;

            $pnl[$round->id] = [
                'sales' => $sales,
                'prizes' => $prizes,
                'refunds' => $refunds,
                'house' => $house,
                // Within a cent = reconciled (the integer-cents math should make it exact).
                'balanced' => abs($sales - $prizes - $refunds - $house) < 0.01,
            ];
        }

        return $pnl;
    }
}
