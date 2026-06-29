@php use App\Enums\RoundStatus; @endphp
<div>
    <header class="mb-4">
        <h1 class="text-2xl font-black gold-text">📜 Round History</h1>
        <p class="text-xs text-slate-400">Past draws and their winners.</p>
    </header>

    @forelse ($rounds as $round)
        <div class="card mb-3 p-4">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="font-bold">{{ $round->title }}</h2>
                    <p class="text-xs text-slate-400">{{ $round->drawn_at?->format('M j, Y · H:i') }}</p>
                </div>
                @if ($round->status === RoundStatus::Cancelled)
                    <span class="badge bg-rose-500/15 text-rose-300">Cancelled</span>
                @else
                    <span class="badge bg-slate-500/15 text-slate-300">Closed</span>
                @endif
            </div>

            @if ($round->winners->isNotEmpty())
                <div class="mt-3 space-y-1.5">
                    @foreach ($round->winners as $i => $w)
                        @php $medal = ['🥇','🥈','🥉'][$i] ?? '🏅'; @endphp
                        <div class="flex items-center justify-between rounded-xl bg-gold-500/10 px-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <span>{{ $medal }}</span>
                                <div>
                                    <p class="font-black text-gold-300">#{{ $w->ticket_number }}</p>
                                    <p class="text-xs text-slate-400">{{ $w->is_split ? $w->ownershipLabel() : $w->owner_name }}</p>
                                </div>
                            </div>
                            <p class="text-sm font-bold text-gold-400">{{ $w->prize_amount }} {{ $round->currency }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-right text-[11px] text-slate-500">Pool {{ $round->prizePool() }} {{ $round->currency }}</p>
            @else
                <p class="mt-3 text-sm text-slate-500">No winner — round cancelled.</p>
            @endif
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-slate-400">No finished rounds yet.</div>
    @endforelse

    <div class="mt-4">{{ $rounds->links() }}</div>
</div>
