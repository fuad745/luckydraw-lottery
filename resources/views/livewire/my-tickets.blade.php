<div>
    <header class="mb-4">
        <h1 class="text-2xl font-black gold-text">🎫 My Tickets</h1>
        <p class="text-xs text-slate-400">Every ticket you own, across all rounds.</p>
    </header>

    @if ($player === null)
        <div class="card p-8 text-center text-sm text-slate-400">
            Open this app from Telegram to see your tickets.
        </div>
    @elseif ($grouped->isEmpty())
        <div class="card p-8 text-center">
            <div class="text-4xl">🎟</div>
            <p class="mt-2 text-sm text-slate-400">You haven't bought any tickets yet.</p>
            <a href="{{ route('home') }}" wire:navigate class="btn-gold mt-4">Buy your first ticket</a>
        </div>
    @else
        @foreach ($grouped as $title => $tickets)
            <section class="mb-4">
                <h2 class="mb-2 text-sm font-semibold text-slate-300">{{ $title }}</h2>
                <div class="space-y-2">
                    @foreach ($tickets as $t)
                        @php
                            $iAmCoOwner = $t->co_owner_telegram_id === $myId;
                            $myStake = $t->is_split ? '½' : 'full';
                            // Your share of the prize if this ticket won.
                            $myPrize = $t->is_winner ? ($t->is_split ? round((float) $t->prize_amount / 2, 2) : (float) $t->prize_amount) : 0;
                            $medal = ['🥇','🥈','🥉'][($t->win_rank ?? 0) - 1] ?? '🏆';
                        @endphp
                        <div class="ticket flex items-center justify-between rounded-xl px-4 py-3">
                            <div>
                                <p class="text-lg font-black text-gold-300">#{{ $t->ticket_number }} <span class="text-[10px] font-medium text-slate-500">{{ $myStake }}</span></p>
                                <p class="text-xs text-slate-400">{{ $t->ownershipLabel() }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                @if ($t->is_winner)
                                    <span class="badge bg-gold-500/20 text-gold-300">{{ $medal }} #{{ $t->win_rank }} · {{ $myPrize }} {{ $t->round->currency }}</span>
                                @elseif ($t->round->status->value === 'closed')
                                    <span class="badge bg-slate-500/15 text-slate-400">No win</span>
                                @endif
                                @if ($t->is_split)
                                    <span class="badge bg-indigo-500/20 text-indigo-300">🤝 {{ $iAmCoOwner ? 'co-owner' : ($t->co_owner_telegram_id ? 'shared' : '½ open') }}</span>
                                @endif
                                <span class="text-[10px] text-slate-500">{{ $t->purchased_at?->diffForHumans() }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
