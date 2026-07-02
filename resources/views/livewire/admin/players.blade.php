<div>
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-100">Players</h1>
        <p class="text-sm text-slate-400">Search, adjust balances, and manage access.</p>
    </div>

    <div class="mb-4">
        <input type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Search name, @username, phone, or Telegram id…">
    </div>

    {{-- Desktop table --}}
    <div class="card hidden overflow-hidden md:block">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-left text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-3">Player</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3 text-right">Won</th>
                    <th class="px-4 py-3 text-right">Tickets</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($players as $p)
                    <tr class="{{ $p->isBanned() ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-100">{{ $p->name }} @if ($p->isBanned())<span class="badge bg-rose-500/15 text-rose-300">banned</span>@endif</p>
                            <p class="text-xs text-slate-500">{{ $p->username ? '@'.$p->username : $p->telegram_id }} · {{ $p->phone }}</p>
                        </td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums text-gold-300">{{ number_format((float) $p->balance, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ number_format((float) $p->total_winnings, 0) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $p->total_tickets_bought }}</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="openAdjust({{ $p->telegram_id }})" class="rounded-lg bg-white/5 px-2.5 py-1.5 text-xs hover:bg-white/10">Adjust</button>
                            <button wire:click="toggleBan({{ $p->telegram_id }})" wire:confirm="Toggle ban for {{ $p->name }}?" class="rounded-lg px-2.5 py-1.5 text-xs {{ $p->isBanned() ? 'text-emerald-300 hover:bg-emerald-500/10' : 'text-rose-300 hover:bg-rose-500/10' }}">{{ $p->isBanned() ? 'Unban' : 'Ban' }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No players found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="space-y-2 md:hidden">
        @forelse ($players as $p)
            <div class="card p-4 {{ $p->isBanned() ? 'opacity-60' : '' }}">
                <div class="flex items-start justify-between">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-100">{{ $p->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $p->username ? '@'.$p->username : $p->telegram_id }}</p>
                    </div>
                    <p class="font-bold tabular-nums text-gold-300">{{ number_format((float) $p->balance, 2) }}</p>
                </div>
                <div class="mt-3 flex gap-2">
                    <button wire:click="openAdjust({{ $p->telegram_id }})" class="flex-1 rounded-lg bg-white/5 py-2 text-xs">Adjust</button>
                    <button wire:click="toggleBan({{ $p->telegram_id }})" wire:confirm="Toggle ban?" class="flex-1 rounded-lg py-2 text-xs {{ $p->isBanned() ? 'text-emerald-300' : 'text-rose-300' }}">{{ $p->isBanned() ? 'Unban' : 'Ban' }}</button>
                </div>
            </div>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">No players found.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $players->links() }}</div>

    {{-- Adjust modal --}}
    @if ($editingPlayer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" wire:key="adjust-modal">
            <div class="card w-full max-w-sm p-5">
                <h3 class="font-semibold text-slate-100">Adjust balance — {{ $editingPlayer->name }}</h3>
                <p class="mt-1 text-xs text-slate-400">Current: {{ number_format((float) $editingPlayer->balance, 2) }} {{ $currency }}</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="label">Amount (use − to debit)</label>
                        <input type="number" step="0.01" wire:model="adjustAmount" class="input" placeholder="e.g. 100 or -50">
                        @error('adjustAmount') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Note (optional)</label>
                        <input type="text" wire:model="adjustNote" class="input" maxlength="120" placeholder="reason">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button wire:click="saveAdjust" wire:loading.attr="disabled" wire:target="saveAdjust" class="btn-gold py-2.5">
                            <x-admin.spinner wire:loading wire:target="saveAdjust" />
                            <span wire:loading.remove wire:target="saveAdjust">Save</span>
                            <span wire:loading wire:target="saveAdjust">Saving…</span>
                        </button>
                        <button wire:click="$set('editing', null)" class="btn-ghost py-2.5">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
