@php use App\Enums\TransactionStatus; @endphp
<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-100">Withdrawals</h1>
            <p class="text-sm text-slate-400">{{ $pendingCount }} awaiting payout.</p>
        </div>
        <select wire:model.live="filter" class="input w-auto py-2 text-sm">
            <option value="pending">Pending</option>
            <option value="completed">Completed</option>
            <option value="rejected">Rejected</option>
            <option value="all">All</option>
        </select>
    </div>

    <div class="space-y-2">
        @forelse ($withdrawals as $w)
            <div class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-lg font-bold tabular-nums text-gold-300">{{ number_format((float) $w->amount, 2) }} {{ $currency }}</p>
                        <p class="truncate text-sm text-slate-300">{{ $w->player?->name ?? $w->telegram_id }}</p>
                        <p class="truncate text-xs text-slate-500">{{ strtoupper($w->provider) }} → {{ $w->reference }} · {{ $w->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="badge bg-white/5 {{ $w->status->color() }}">{{ $w->status->label() }}</span>
                </div>
                @if ($w->status === TransactionStatus::Pending)
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:max-w-xs">
                        <button wire:click="approve({{ $w->id }})" wire:confirm="Confirm you have paid this out?"
                                wire:loading.attr="disabled" wire:target="approve" class="btn-gold py-2 text-xs">Mark paid</button>
                        <button wire:click="reject({{ $w->id }})" wire:confirm="Reject and refund to wallet?"
                                wire:loading.attr="disabled" wire:target="reject" class="btn-ghost py-2 text-xs text-rose-300">Reject & refund</button>
                    </div>
                @endif
            </div>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">Nothing here.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $withdrawals->links() }}</div>
</div>
