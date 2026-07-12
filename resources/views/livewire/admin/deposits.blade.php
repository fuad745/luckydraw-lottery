@php use App\Enums\TransactionStatus; @endphp
<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-100">Manual deposits</h1>
            <p class="text-sm text-slate-400">{{ $pendingCount }} awaiting review. Check the pasted SMS against your account statement before approving.</p>
        </div>
        <select wire:model.live="filter" class="input w-auto py-2 text-sm">
            <option value="pending">Pending</option>
            <option value="completed">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="all">All</option>
        </select>
    </div>

    <div class="space-y-2">
        @forelse ($deposits as $d)
            <div class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-200">{{ $d->player?->name ?? $d->telegram_id }}</p>
                        <p class="truncate text-xs text-slate-400">
                            {{ $d->meta['name'] ?? '—' }} · {{ $d->meta['phone'] ?? '—' }} · {{ strtoupper((string) $d->provider) }}
                        </p>
                        <p class="text-xs text-slate-500">#{{ $d->id }} · {{ $d->created_at->diffForHumans() }}@if ($d->reference) · Ref {{ $d->reference }}@endif</p>
                    </div>
                    <span class="badge bg-white/5 {{ $d->status->color() }}">{{ $d->status === TransactionStatus::Completed ? 'Approved' : $d->status->label() }}</span>
                </div>

                @if (($d->meta['sms'] ?? '') !== '')
                    <pre class="mt-3 max-h-40 overflow-auto whitespace-pre-wrap rounded-xl bg-black/30 p-3 text-xs text-slate-300">{{ $d->meta['sms'] }}</pre>
                @endif

                @if ($d->status === TransactionStatus::Pending)
                    <div class="mt-3 flex flex-wrap items-end gap-2">
                        <div>
                            <label class="label" for="amt-{{ $d->id }}">Amount to credit ({{ $currency }})</label>
                            <input id="amt-{{ $d->id }}" type="number" step="0.01" min="0" class="input w-36 py-2 text-sm"
                                   wire:model="amounts.{{ $d->id }}" placeholder="{{ number_format((float) $d->amount, 2, '.', '') }}">
                        </div>
                        <button wire:click="approve({{ $d->id }})" wire:confirm="Credit this amount to the player's balance?"
                                wire:loading.attr="disabled" wire:target="approve" class="btn-gold py-2 text-xs">Approve & credit</button>
                        <button wire:click="reject({{ $d->id }})" wire:confirm="Reject this deposit claim?"
                                wire:loading.attr="disabled" wire:target="reject" class="btn-ghost py-2 text-xs text-rose-300">Reject</button>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">Amount is pre-read from the SMS — correct it if it doesn't match your statement.</p>
                @else
                    <p class="mt-2 text-sm font-bold tabular-nums text-gold-300">{{ number_format((float) $d->amount, 2) }} {{ $currency }}</p>
                @endif
            </div>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">Nothing here.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $deposits->links() }}</div>
</div>
