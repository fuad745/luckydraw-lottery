<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-100">Transactions</h1>
            <p class="text-sm text-slate-400">Full wallet ledger.</p>
        </div>
        <a href="{{ route('admin.transactions.export', ['type' => $type, 'search' => $search]) }}"
           class="rounded-xl bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 ring-1 ring-white/10 hover:bg-white/10">Export CSV</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="type" class="input w-auto py-2 text-sm">
            @foreach ($types as $t)
                <option value="{{ $t }}">{{ ucfirst($t) }}</option>
            @endforeach
        </select>
        <input type="search" wire:model.live.debounce.300ms="search" class="input flex-1" placeholder="Search name, reference, Telegram id…">
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Player</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3 hidden sm:table-cell">When</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($transactions as $t)
                    <tr>
                        <td class="px-4 py-3 tabular-nums text-slate-500">{{ $t->id }}</td>
                        <td class="px-4 py-3 text-slate-200">{{ $t->type->label() }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $t->player?->name ?? $t->telegram_id }}</td>
                        <td class="px-4 py-3"><span class="{{ $t->status->color() }} text-xs">{{ $t->status->label() }}</span></td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $t->signedAmount() >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $t->signedAmount() >= 0 ? '+' : '−' }}{{ number_format(abs($t->signedAmount()), 2) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-400">{{ $t->balance_after !== null ? number_format((float) $t->balance_after, 2) : '—' }}</td>
                        <td class="hidden px-4 py-3 text-xs text-slate-500 sm:table-cell">{{ $t->created_at->format('M j, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No transactions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>
</div>
