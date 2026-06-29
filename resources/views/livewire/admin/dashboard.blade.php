@php
    $max = max(1.0, collect($chart)->flatMap(fn ($d) => [$d['sales'], $d['house']])->max());
    $hasData = collect($chart)->contains(fn ($d) => $d['sales'] > 0 || $d['house'] > 0);
    $kpiCards = [
        ['Players', number_format($kpis['players']), 'text-slate-100'],
        ['Wallet liabilities', number_format($kpis['liabilities'], 2).' '.$currency, 'text-amber-300'],
        ['House earnings', number_format($kpis['house'], 2).' '.$currency, 'text-emerald-300'],
        ['Paid out', number_format($kpis['paid_out'], 2).' '.$currency, 'text-slate-100'],
        ['Deposits', number_format($kpis['deposits'], 2).' '.$currency, 'text-slate-100'],
        ['Withdrawn', number_format($kpis['withdrawn'], 2).' '.$currency, 'text-slate-100'],
        ['Rounds played', number_format($kpis['rounds']), 'text-slate-100'],
        ['Pending payouts', $kpis['pending_withdrawals'].' · '.number_format($kpis['pending_amount'], 0).' '.$currency, 'text-rose-300'],
    ];
@endphp
<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-100">Dashboard</h1>
            <p class="text-sm text-slate-400">Operator overview</p>
        </div>
        @if ($current)
            <a href="{{ route('admin.rounds') }}" class="rounded-xl bg-gold-500/15 px-3 py-2 text-xs font-semibold text-gold-300 ring-1 ring-gold-500/30">
                ● {{ $current->title }} ({{ $current->status->label() }})
            </a>
        @endif
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($kpiCards as [$label, $value, $color])
            <div class="card p-4">
                <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold tabular-nums {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Revenue chart --}}
    <div class="card mt-5 p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold text-slate-100">Last 14 days</h2>
            <div class="flex gap-4 text-xs text-slate-400">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-gold-500"></span>Ticket sales</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-violet-500"></span>House cut</span>
            </div>
        </div>

        @if ($hasData)
            <div class="flex h-44 items-end gap-1.5" role="img" aria-label="Daily ticket sales and house cut for the last 14 days">
                @foreach ($chart as $d)
                    <div class="flex h-full flex-1 flex-col items-center justify-end">
                        <div class="flex w-full items-end justify-center gap-0.5" style="height: 100%">
                            <div class="w-1/2 rounded-t bg-gold-500/90 transition-all hover:bg-gold-400"
                                 style="height: {{ max(1, $d['sales'] / $max * 100) }}%"
                                 title="{{ $d['label'] }} · sales {{ number_format($d['sales'], 2) }} {{ $currency }}"></div>
                            <div class="w-1/2 rounded-t bg-violet-500/90 transition-all hover:bg-violet-400"
                                 style="height: {{ max(1, $d['house'] / $max * 100) }}%"
                                 title="{{ $d['label'] }} · house {{ number_format($d['house'], 2) }} {{ $currency }}"></div>
                        </div>
                        <span class="mt-1 hidden text-[9px] text-slate-500 sm:block">{{ \Illuminate\Support\Str::after($d['label'], ' ') }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex h-44 items-center justify-center text-sm text-slate-500">No activity in the last 14 days yet.</div>
        @endif
    </div>

    {{-- Recent activity --}}
    <div class="card mt-5 p-5">
        <h2 class="mb-3 font-semibold text-slate-100">Recent transactions</h2>
        <div class="divide-y divide-white/5">
            @forelse ($recent as $t)
                <div class="flex items-center justify-between py-2.5 text-sm">
                    <div>
                        <p class="font-medium text-slate-200">{{ $t->type->label() }}</p>
                        <p class="text-xs text-slate-500">{{ $t->player?->name ?? $t->telegram_id }} · {{ $t->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="font-semibold tabular-nums {{ $t->signedAmount() >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $t->signedAmount() >= 0 ? '+' : '−' }}{{ number_format(abs($t->signedAmount()), 2) }}
                    </span>
                </div>
            @empty
                <p class="py-4 text-sm text-slate-500">No transactions yet.</p>
            @endforelse
        </div>
    </div>
</div>
