<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\RoundStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Player;
use App\Models\Round;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
final class Dashboard extends Component
{
    public function render()
    {
        $currency = config('lottery.currency', 'ETB');

        $kpis = [
            'players' => Player::count(),
            'liabilities' => (float) Player::sum('balance'),
            'house' => (float) Round::sum('admin_cut'),
            'paid_out' => (float) Transaction::where('type', TransactionType::Winning->value)->sum('amount'),
            // Completed only — pending/rejected manual claims are unverified
            // player-typed amounts, not money that arrived.
            'deposits' => (float) Transaction::where('type', TransactionType::Deposit->value)->where('status', TransactionStatus::Completed->value)->sum('amount'),
            'withdrawn' => (float) Transaction::where('type', TransactionType::Withdrawal->value)->where('status', TransactionStatus::Completed->value)->sum('amount'),
            'rounds' => Round::where('status', RoundStatus::Closed->value)->count(),
            'pending_withdrawals' => Transaction::where('type', TransactionType::Withdrawal->value)->where('status', TransactionStatus::Pending->value)->count(),
            'pending_amount' => (float) Transaction::where('type', TransactionType::Withdrawal->value)->where('status', TransactionStatus::Pending->value)->sum('amount'),
        ];

        return view('livewire.admin.dashboard', [
            'currency' => $currency,
            'kpis' => $kpis,
            'current' => Round::current(),
            'chart' => $this->dailySeries(14),
            // Rejected rows stay on the Transactions ledger, not the pulse feed.
            'recent' => Transaction::with('player')->where('status', '!=', TransactionStatus::Rejected->value)->latest('id')->limit(8)->get(),
        ]);
    }

    /** Daily ticket-sales vs house-cut series for the last $days days. */
    private function dailySeries(int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);

        // Bucket in PHP so it stays portable across SQLite/MySQL.
        $sales = Transaction::where('type', TransactionType::Purchase->value)
            ->where('created_at', '>=', $start)->get(['amount', 'created_at']);
        $rounds = Round::where('status', RoundStatus::Closed->value)
            ->whereNotNull('drawn_at')->where('drawn_at', '>=', $start)->get(['admin_cut', 'drawn_at']);

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $series[$key] = ['label' => $date->format('M j'), 'sales' => 0.0, 'house' => 0.0];
        }

        foreach ($sales as $t) {
            $k = $t->created_at->toDateString();
            if (isset($series[$k])) {
                $series[$k]['sales'] += (float) $t->amount;
            }
        }
        foreach ($rounds as $r) {
            $k = $r->drawn_at->toDateString();
            if (isset($series[$k])) {
                $series[$k]['house'] += (float) $r->admin_cut;
            }
        }

        return array_values($series);
    }
}
