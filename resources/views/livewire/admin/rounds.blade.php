@php use App\Enums\RoundStatus; @endphp
<div wire:poll.5s.visible>
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-100">Rounds</h1>
        <p class="text-sm text-slate-400">Create and manage lottery rounds.</p>
    </div>

    @if ($flash)
        <div class="card mb-4 border-emerald-500/30 p-3 text-sm font-semibold text-emerald-300">{{ $flash }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- Current round --}}
        <section class="card p-5">
            <h2 class="mb-3 font-semibold text-slate-100">Live round</h2>
            @if ($current === null)
                <p class="text-sm text-slate-400">No active round. Start one →</p>
            @else
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-lg font-bold text-slate-100">{{ $current->title }}</p>
                        <p class="text-xs {{ $current->status->color() }}">● {{ $current->status->label() }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-black tabular-nums text-gold-300">{{ $current->prizePool() }}</p>
                        <p class="text-[10px] uppercase text-slate-500">{{ $current->currency }} pool</p>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-white/5 p-2"><p class="text-lg font-bold tabular-nums">{{ rtrim(rtrim(number_format($current->soldUnits(), 1), '0'), '.') }}</p><p class="text-[10px] text-slate-500">sold</p></div>
                    <div class="rounded-lg bg-white/5 p-2"><p class="text-lg font-bold tabular-nums">{{ $current->ticketsRemaining() }}</p><p class="text-[10px] text-slate-500">left</p></div>
                    <div class="rounded-lg bg-white/5 p-2"><p class="text-lg font-bold tabular-nums">{{ $current->winners_count }}</p><p class="text-[10px] text-slate-500">winners</p></div>
                </div>
                @if ($current->status === RoundStatus::Open)
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button wire:click="startDrawNow" wire:confirm="Start the draw now with tickets sold so far?" class="btn-gold py-2.5">Draw now</button>
                        <button wire:click="cancelRound" wire:confirm="Cancel this round? Buyers are refunded." class="btn-ghost py-2.5 text-rose-300">Cancel & refund</button>
                    </div>
                @elseif ($current->status === RoundStatus::Drawing)
                    <p class="mt-4 text-center text-sm text-amber-300">Draw in progress…</p>
                @endif
            @endif

            <div class="mb-2 mt-6 flex items-center justify-between">
                <h2 class="font-semibold text-slate-100">Recent rounds — P&amp;L</h2>
                <span class="text-[10px] uppercase tracking-wide text-slate-500">sales · prizes · house</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wide text-slate-500">
                            <th class="py-1 pr-2 font-medium">Round</th>
                            <th class="py-1 px-1 text-right font-medium">Sales</th>
                            <th class="py-1 px-1 text-right font-medium">Prizes</th>
                            <th class="py-1 px-1 text-right font-medium">Refunds</th>
                            <th class="py-1 pl-1 text-right font-medium">House</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($recent as $r)
                            @php $p = $pnl[$r->id] ?? ['sales' => 0, 'prizes' => 0, 'refunds' => 0, 'house' => 0, 'balanced' => true]; @endphp
                            <tr>
                                <td class="py-2 pr-2">
                                    <span class="text-slate-200">{{ $r->title }}</span>
                                    <span class="ml-1 text-[10px] {{ $r->status->color() }}">● {{ $r->status->label() }}</span>
                                    @unless ($p['balanced'])
                                        <span class="ml-1 text-[10px] text-rose-400" title="Sales − prizes − refunds ≠ house cut">⚠︎</span>
                                    @endunless
                                </td>
                                <td class="py-2 px-1 text-right tabular-nums text-emerald-300">{{ number_format($p['sales'], 2) }}</td>
                                <td class="py-2 px-1 text-right tabular-nums text-rose-300">{{ number_format($p['prizes'], 2) }}</td>
                                <td class="py-2 px-1 text-right tabular-nums text-slate-400">{{ number_format($p['refunds'], 2) }}</td>
                                <td class="py-2 pl-1 text-right tabular-nums font-semibold text-gold-300">{{ number_format($p['house'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-sm text-slate-500">No rounds yet.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($recent))
                        <tfoot>
                            <tr class="border-t border-white/10 text-[11px] font-semibold">
                                <td class="py-2 pr-2 text-slate-400">Totals</td>
                                <td class="py-2 px-1 text-right tabular-nums text-emerald-300">{{ number_format(collect($pnl)->sum('sales'), 2) }}</td>
                                <td class="py-2 px-1 text-right tabular-nums text-rose-300">{{ number_format(collect($pnl)->sum('prizes'), 2) }}</td>
                                <td class="py-2 px-1 text-right tabular-nums text-slate-400">{{ number_format(collect($pnl)->sum('refunds'), 2) }}</td>
                                <td class="py-2 pl-1 text-right tabular-nums text-gold-300">{{ number_format(collect($pnl)->sum('house'), 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        {{-- Create round --}}
        <section class="card p-5">
            <h2 class="mb-3 font-semibold text-slate-100">Start a new round</h2>
            <div class="space-y-3">
                <div>
                    <label class="label">Round title</label>
                    <input type="text" wire:model="title" class="input" placeholder="Friday Mega Draw">
                    @error('title') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Total tickets</label>
                        <input type="number" wire:model.live="totalTickets" class="input" min="2" max="1000">
                        @error('totalTickets') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">Ticket price</label>
                        <input type="number" step="0.01" wire:model.live="ticketPrice" class="input" min="1">
                        @error('ticketPrice') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Currency</label>
                        <input type="text" wire:model="currency" class="input" maxlength="8">
                    </div>
                    <div>
                        <label class="label">Winners</label>
                        <input type="number" wire:model.live="winnersCount" class="input" min="1" max="5">
                    </div>
                </div>

                <div class="rounded-xl border border-white/10 p-3">
                    <p class="label">Prize split</p>
                    <div class="space-y-2">
                        @foreach ($tiers as $i => $tier)
                            <div class="flex items-center gap-2">
                                <span class="w-8 text-xs text-slate-400">#{{ $i + 1 }}</span>
                                <select wire:model.live="tiers.{{ $i }}.type" class="input flex-1 py-2 text-sm">
                                    <option value="percent">% of pot</option>
                                    <option value="ticket_price">1 ticket price</option>
                                </select>
                                @if (($tier['type'] ?? 'percent') === 'percent')
                                    <div class="relative w-24">
                                        <input type="number" step="1" min="0" max="100" wire:model.live="tiers.{{ $i }}.value" class="input py-2 pr-6 text-right text-sm">
                                        <span class="absolute right-2 top-2.5 text-xs text-slate-500">%</span>
                                    </div>
                                @else
                                    <span class="w-24 text-right text-xs text-slate-400">{{ $ticketPrice }} {{ $currency }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 rounded-lg bg-white/5 p-2 text-xs">
                        <p class="mb-1 text-slate-400">Preview @ full sell-out (pot {{ $previewPot }} {{ $currency }}):</p>
                        @foreach ($previewTiers as $i => $amt)
                            <div class="flex justify-between"><span>#{{ $i + 1 }}</span><span class="tabular-nums text-gold-300">{{ $amt }} {{ $currency }}</span></div>
                        @endforeach
                        <div class="mt-1 flex justify-between border-t border-white/10 pt-1 text-slate-400"><span>House</span><span class="tabular-nums">{{ $previewAdmin }} {{ $currency }}</span></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center justify-between rounded-xl border border-white/10 p-3 text-sm"><span>Half tickets</span><input type="checkbox" wire:model="allowHalf" class="h-5 w-5 accent-[var(--color-gold-500)]"></label>
                    <label class="flex items-center justify-between rounded-xl border border-white/10 p-3 text-sm"><span>Auto-draw</span><input type="checkbox" wire:model="autoDraw" class="h-5 w-5 accent-[var(--color-gold-500)]"></label>
                </div>

                <div class="rounded-xl border border-white/10 p-3">
                    <label class="flex items-center justify-between text-sm"><span>Auto-start next round</span><input type="checkbox" wire:model.live="autoRestart" class="h-5 w-5 accent-[var(--color-gold-500)]"></label>
                    @if ($autoRestart)
                        <div class="mt-2"><label class="label">Restart delay (minutes)</label><input type="number" wire:model="restartDelay" class="input" min="1" max="1440"></div>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Deadline (optional)</label><input type="datetime-local" wire:model="deadline" class="input text-sm"></div>
                    <div><label class="label">Channel (optional)</label><input type="text" wire:model="channelId" class="input text-sm" placeholder="@channel"></div>
                </div>

                <button wire:click="createRound" wire:loading.attr="disabled" class="btn-gold w-full">Start round</button>
            </div>
        </section>
    </div>
</div>
