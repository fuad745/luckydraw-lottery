<div wire:poll.60s.visible>
    <header class="mb-4">
        <h1 class="text-2xl font-black gold-text">🏆 {{ __('Hall of Winners') }}</h1>
        <p class="text-xs text-slate-400">{{ __('All-time LuckyDraw champions.') }}</p>
    </header>

    @forelse ($winners as $i => $player)
        @php $medal = [0 => '🥇', 1 => '🥈', 2 => '🥉'][$i] ?? null; @endphp
        <div class="card mb-2 flex items-center gap-3 p-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/5 text-sm font-bold">
                {{ $medal ?? ('#'.($i + 1)) }}
            </div>
            <div class="flex-1">
                <p class="font-semibold">{{ $player->name }}</p>
                <p class="text-xs text-slate-400">{{ trans_choice(':count win|:count wins', $player->total_wins, ['count' => $player->total_wins]) }} · {{ __(':count played', ['count' => $player->total_tickets_bought]) }}</p>
            </div>
            <div class="text-right">
                <p class="text-lg font-black text-gold-300">{{ number_format((float) $player->total_winnings) }}</p>
                <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ __(':currency won', ['currency' => $currency]) }}</p>
            </div>
        </div>
    @empty
        <div class="card p-8 text-center text-sm text-slate-400">{{ __('No winners yet — be the first! 🍀') }}</div>
    @endforelse
</div>
