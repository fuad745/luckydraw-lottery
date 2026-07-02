@php use App\Enums\RoundStatus; @endphp
<div wire:poll.5s.visible>
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-100">Rounds</h1>
            <p class="text-sm text-slate-400">Monitor the live round and past performance.</p>
        </div>
        <a href="{{ route('admin.rounds.create') }}" class="btn-gold shrink-0 px-4 py-2.5 text-sm">＋ New round</a>
    </div>

    {{-- Live round --}}
    <section class="card mb-5 p-5">
        <h2 class="mb-3 font-semibold text-slate-100">Live round</h2>
        @if ($current === null)
            <div class="rounded-xl border border-dashed border-white/10 p-6 text-center">
                <p class="text-sm text-slate-400">No active round right now.</p>
                <a href="{{ route('admin.rounds.create') }}" class="btn-gold mt-3 px-5 py-2 text-sm">Start a new round</a>
            </div>
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
            <div class="mt-4 grid grid-cols-3 gap-2 text-center sm:max-w-sm">
                <div class="rounded-lg bg-white/5 p-2"><p class="text-lg font-bold tabular-nums">{{ rtrim(rtrim(number_format($current->soldUnits(), 1), '0'), '.') }}</p><p class="text-[10px] text-slate-500">sold</p></div>
                <div class="rounded-lg bg-white/5 p-2"><p class="text-lg font-bold tabular-nums">{{ $current->ticketsRemaining() }}</p><p class="text-[10px] text-slate-500">left</p></div>
                <div class="rounded-lg bg-white/5 p-2"><p class="text-lg font-bold tabular-nums">{{ $current->winners_count }}</p><p class="text-[10px] text-slate-500">winners</p></div>
            </div>
            @if ($current->status === RoundStatus::Open)
                <div class="mt-4 flex flex-wrap gap-2">
                    <button wire:click="startDrawNow" wire:confirm="Start the draw now with tickets sold so far?"
                            wire:loading.attr="disabled" wire:target="startDrawNow" class="btn-gold px-5 py-2.5">
                        <x-admin.spinner wire:loading wire:target="startDrawNow" />
                        Draw now
                    </button>
                    <button wire:click="cancelRound" wire:confirm="Cancel this round? Buyers are refunded."
                            wire:loading.attr="disabled" wire:target="cancelRound" class="btn-ghost px-5 py-2.5 text-rose-300">
                        <x-admin.spinner wire:loading wire:target="cancelRound" />
                        Cancel &amp; refund
                    </button>
                    @if ($current->ticketsSold() === 0)
                        <button wire:click="deleteRound({{ $current->id }})"
                                wire:confirm="Delete this round permanently? No tickets were sold, so nothing is refunded."
                                wire:loading.attr="disabled" wire:target="deleteRound"
                                class="btn-ghost px-5 py-2.5 text-rose-300">
                            <x-admin.spinner wire:loading wire:target="deleteRound" />
                            🗑 Delete
                        </button>
                    @endif
                </div>
            @elseif ($current->status === RoundStatus::Drawing)
                <p class="mt-4 flex items-center gap-2 text-sm text-amber-300">
                    <x-admin.spinner class="h-4 w-4 animate-spin" /> Draw in progress…
                </p>
            @endif
        @endif
    </section>

    {{-- Recent rounds — P&L --}}
    <section class="card p-5">
        <div class="mb-2 flex items-center justify-between">
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
                        <th class="py-1 px-1 text-right font-medium">House</th>
                        <th class="py-1 pl-1 text-right font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($recent as $r)
                        @php
                            $p = $pnl[$r->id] ?? ['sales' => 0, 'prizes' => 0, 'refunds' => 0, 'house' => 0, 'balanced' => true];
                            // Deletable = nobody ever bought in (no tickets, no money movement).
                            $deletable = $r->tickets_count === 0 && $p['sales'] == 0 && $p['prizes'] == 0 && $p['refunds'] == 0;
                        @endphp
                        <tr wire:key="round-{{ $r->id }}">
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
                            <td class="py-2 px-1 text-right tabular-nums font-semibold text-gold-300">{{ number_format($p['house'], 2) }}</td>
                            <td class="py-2 pl-1 text-right">
                                @if ($deletable)
                                    <button wire:click="deleteRound({{ $r->id }})"
                                            wire:confirm="Delete '{{ $r->title }}' permanently? It has no players, so nothing is refunded."
                                            wire:loading.attr="disabled" wire:target="deleteRound"
                                            class="rounded-lg p-1.5 text-rose-300 transition hover:bg-rose-500/10"
                                            title="Delete round (no players)" aria-label="Delete {{ $r->title }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-sm text-slate-500">No rounds yet.</td></tr>
                    @endforelse
                </tbody>
                @if (count($recent))
                    <tfoot>
                        <tr class="border-t border-white/10 text-[11px] font-semibold">
                            <td class="py-2 pr-2 text-slate-400">Totals</td>
                            <td class="py-2 px-1 text-right tabular-nums text-emerald-300">{{ number_format(collect($pnl)->sum('sales'), 2) }}</td>
                            <td class="py-2 px-1 text-right tabular-nums text-rose-300">{{ number_format(collect($pnl)->sum('prizes'), 2) }}</td>
                            <td class="py-2 px-1 text-right tabular-nums text-slate-400">{{ number_format(collect($pnl)->sum('refunds'), 2) }}</td>
                            <td class="py-2 px-1 text-right tabular-nums text-gold-300">{{ number_format(collect($pnl)->sum('house'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
</div>
