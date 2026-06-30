@php use App\Enums\RoundStatus; @endphp
<div wire:poll.3s.visible
     x-data="{
        toast: null,
        toastType: 'info',
        share: null,
        init() {
            Livewire.on('toast', (e) => {
                const d = Array.isArray(e) ? e[0] : e;
                this.toast = d.message;
                this.toastType = d.type || 'info';
                clearTimeout(this._t);
                this._t = setTimeout(() => this.toast = null, this.toastType === 'error' ? 6000 : 3500);
            });
            Livewire.on('purchased', (e) => { this.share = Array.isArray(e)?e[0]:e; });
        }
     }">

    {{-- Header --}}
    <header class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight gold-text">LuckyDraw 🎰</h1>
            <p class="text-xs text-slate-400">{{ __('Tickets. Luck. Glory.') }}</p>
        </div>
        <a href="{{ route('settings') }}" wire:navigate aria-label="{{ __('Settings') }}"
           class="rounded-xl border border-white/10 bg-white/5 p-2.5 text-slate-300 hover:bg-white/10">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.241.437-.613.43-.992a7.723 7.723 0 0 1 0-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
        </a>
    </header>

    {{-- Toast (tap to dismiss; colour + icon distinguish success / error) --}}
    <div x-show="toast" x-transition x-cloak role="status" aria-live="polite"
         @click="toast = null"
         class="card mb-3 flex cursor-pointer items-start gap-2 p-3 text-sm font-semibold"
         :class="toastType === 'error' ? 'border-rose-500/50 text-rose-200' : (toastType === 'success' ? 'border-emerald-500/50 text-emerald-200' : 'border-gold-500/40 text-gold-300')">
        <span aria-hidden="true" x-text="toastType === 'error' ? '⚠️' : (toastType === 'success' ? '✅' : 'ℹ️')"></span>
        <span class="flex-1 text-left" x-text="toast"></span>
    </div>

    @if ($round === null)
        <div class="card mt-10 p-8 text-center">
            <div class="text-5xl">🕒</div>
            <h2 class="mt-3 text-lg font-bold">{{ __("No active draw right now") }}</h2>
            <p class="mt-1 text-sm text-slate-400">{{ __("Check back soon — a new LuckyDraw round is on its way.") }}</p>
        </div>

    @elseif ($round->status === RoundStatus::Drawing)
        {{-- ===== 3D draw machine ===== --}}
        <div class="card mt-6 p-6 text-center">
            <p class="text-xs uppercase tracking-widest text-slate-400">{{ $round->title }}</p>
            <h2 class="mt-1 text-xl font-black gold-text">{{ __('Drawing the winners…') }}</h2>

            <div class="lotto-stage my-6">
                <div class="lotto-drum">
                    @php $ballCount = min(16, max(8, (int) $round->total_tickets)); @endphp
                    @for ($i = 0; $i < $ballCount; $i++)
                        {{-- Stable pseudo-number per ball so the poll re-render doesn't make them jump. --}}
                        <span class="lotto-ball {{ $i % 2 ? 'alt' : '' }}">{{ ($i * 7 + $round->id) % $round->total_tickets + 1 }}</span>
                    @endfor
                </div>
                {{-- chute --}}
                <div class="mx-auto mt-2 h-6 w-10 rounded-b-xl border border-t-0 border-gold-500/40 bg-ink-900"></div>
            </div>

            <p class="text-sm text-slate-300">{{ __('Selecting') }} <b class="text-gold-400">{{ $round->winners_count }}</b> {{ __('winning ball(s) from a pool of') }}
                <b class="text-gold-400">{{ number_format($round->prizePool(), 2) }} {{ $round->currency }}</b></p>
            <div class="mt-4 flex justify-center gap-1.5">
                <span class="h-2 w-2 animate-ping rounded-full bg-gold-500"></span>
                <span class="h-2 w-2 animate-ping rounded-full bg-gold-500 [animation-delay:150ms]"></span>
                <span class="h-2 w-2 animate-ping rounded-full bg-gold-500 [animation-delay:300ms]"></span>
            </div>
        </div>

    @elseif ($round->status === RoundStatus::Closed && $winners->isNotEmpty())
        {{-- ===== Cinematic winner reveal (balls pulled one-by-one) ===== --}}
        @php
            $iWon = $winners->contains(fn ($w) => in_array($auth->id(), $w->holderTelegramIds(), true));
            $payload = [
                'roundId' => $round->id,
                'total' => $round->total_tickets,
                'iWon' => $iWon,
                'winners' => $winners->values()->map(fn ($w, $i) => [
                    'n' => $w->ticket_number,
                    'rank' => $w->win_rank,
                    'medal' => ['🥇', '🥈', '🥉'][$i] ?? '🏅',
                    'name' => $w->is_split ? $w->ownershipLabel() : $w->owner_name,
                    'prize' => (string) $w->prize_amount,
                ])->all(),
            ];
        @endphp
        <div wire:key="reveal-{{ $round->id }}" x-data="reveal(@js($payload))"
             class="card mt-6 p-6 text-center">
            <p class="text-xs uppercase tracking-widest text-slate-400">{{ $round->title }} — {{ __('Results') }}</p>
            <h2 class="mt-1 text-lg font-black gold-text"
                x-text="stage === 'done' ? '{{ trans_choice(':count winning ball drawn!|:count winning balls drawn!', $winners->count(), ['count' => $winners->count()]) }}' : '{{ __('Drawing the winners…') }}'"></h2>

            {{-- Spinning drum while drawing --}}
            <div class="lotto-stage my-4" x-show="stage !== 'done'" x-transition>
                <div class="lotto-drum" style="width:150px;height:150px;">
                    @for ($i = 0; $i < 10; $i++)
                        <span class="lotto-ball {{ $i % 2 ? 'alt' : '' }}" style="width:28px;height:28px;margin:-14px 0 0 -14px;font-size:11px;">{{ random_int(1, $round->total_tickets) }}</span>
                    @endfor
                </div>
                <div class="mx-auto mt-2 h-5 w-9 rounded-b-xl border border-t-0 border-gold-500/40 bg-ink-900"></div>
            </div>

            {{-- Drawn balls --}}
            <div class="mt-2 flex flex-wrap items-start justify-center gap-4">
                <template x-for="(w, i) in winners" :key="i">
                    <div class="flex w-20 flex-col items-center" x-show="shown > i" x-transition>
                        <div class="win-ball" :class="locked[i] && 'ball-drop'"><span x-text="display[i]"></span></div>
                        <div x-show="locked[i]" x-transition class="mt-1">
                            <span class="text-base" x-text="w.medal"></span>
                            <p class="max-w-[80px] truncate text-[11px] text-slate-400" x-text="w.name"></p>
                            <p class="text-xs font-bold text-gold-400"><span x-text="w.prize"></span> {{ $round->currency }}</p>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="stage === 'done'" x-transition>
                <p class="mt-5 text-sm text-slate-300">{{ __('Prize pool') }}</p>
                <p class="text-2xl font-black text-gold-400">{{ number_format($round->prizePool(), 2) }} {{ $round->currency }}</p>
                @if ($iWon)
                    <div class="pulse-gold mt-4 rounded-xl bg-gold-500/15 p-3 text-sm font-bold text-gold-300">
                        🎉 {{ __("You're a winner! Check your DM from the bot.") }}
                    </div>
                @endif
                <a href="{{ route('history') }}" wire:navigate class="btn-ghost mt-5 w-full">📜 {{ __('Past rounds') }}</a>
            </div>
        </div>

    @else
        {{-- ===== OPEN: stats + board + buy ===== --}}
        @php $pct = $round->total_tickets > 0 ? min(100, round($soldUnits / $round->total_tickets * 100)) : 0; @endphp

        <section class="card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-bold">{{ $round->title }}</h2>
                    <p class="text-sm text-slate-400">{{ $round->ticket_price }} {{ $round->currency }} / {{ __('ticket') }}
                        @if ($round->allow_half_tickets)<span class="text-slate-500">· {{ __('½ allowed') }}</span>@endif
                    </p>
                </div>
                <span class="badge bg-emerald-500/15 text-emerald-300">● {{ __('Open') }}</span>
            </div>

            <div class="mt-4 rounded-xl bg-gradient-to-br from-gold-500/15 to-transparent p-4 text-center">
                <p class="text-xs uppercase tracking-widest text-slate-400">{{ __("Live prize pool") }}</p>
                <p class="text-3xl font-black tabular-nums gold-text"><span x-data="counter({{ $prizePool }})" x-text="display">{{ $prizePool }}</span> {{ $round->currency }}</p>
                @if ($round->winners_count > 1)
                    <p class="mt-1 text-xs text-slate-400">{{ __(':count winners share the pot', ['count' => $round->winners_count]) }}</p>
                @endif
            </div>

            <div class="mt-4">
                <div class="mb-1 flex justify-between text-xs text-slate-400">
                    <span>{{ rtrim(rtrim(number_format($soldUnits, 1), '0'), '.') }}/{{ $round->total_tickets }} {{ __('sold') }}</span>
                    <span>{{ $remaining }} {{ __('left') }}</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-gradient-to-r from-gold-400 to-gold-600 transition-all" style="width: {{ $pct }}%"></div>
                </div>
            </div>

            @if ($round->draw_deadline)
                <div class="mt-4 rounded-xl border border-white/10 p-3 text-center"
                     x-data="{ target: {{ $round->draw_deadline->getTimestamp() }} * 1000, left: '', _id: null,
                        tick(){ let d=Math.max(0,this.target-Date.now()); let h=Math.floor(d/3.6e6),m=Math.floor(d%3.6e6/6e4),s=Math.floor(d%6e4/1e3);
                        this.left=`${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`; },
                        init(){ this.tick(); this._id=setInterval(()=>this.tick(),1000); },
                        destroy(){ clearInterval(this._id); } }">
                    <p class="text-xs uppercase tracking-widest text-slate-400">{{ __('Draw in') }}</p>
                    <p class="font-mono text-xl font-bold text-gold-400" x-text="left">--:--:--</p>
                </div>
            @endif
        </section>

        {{-- Board + buy: all client-side selection for instant taps --}}
        <section class="card mt-4 p-4"
                 x-data="{
                    mode: 'full',
                    selFull: [],
                    selHalf: [],
                    inspectMsg: null,
                    price: {{ (float) $round->ticket_price }},
                    allowHalf: {{ $round->allow_half_tickets ? 'true' : 'false' }},
                    board: $wire.entangle('board'),
                    balance: {{ (float) ($player?->balance ?? 0) }},
                    get units() { return this.selFull.length + this.selHalf.length * 0.5; },
                    get costNum() { return this.units * this.price; },
                    get cost() { return this.costNum.toFixed(2); },
                    get canAfford() { return this.costNum <= this.balance; },
                    flip(arr, n) { const i = arr.indexOf(n); if (i >= 0) arr.splice(i, 1); else arr.push(n); },
                    isSel(c) { return this.selFull.includes(c.n) || this.selHalf.includes(c.n); },
                    selHalfQ(c) { return this.selHalf.includes(c.n); },
                    tap(c) {
                        if (c.s === 'taken' || (c.s === 'half' && c.mine)) { this.inspectMsg = '#' + c.n + ' — ' + (c.who || 'sold'); window.luckyHaptic && window.luckyHaptic('selection'); return; }
                        if (c.s === 'free') { (this.mode === 'half' && this.allowHalf) ? this.flip(this.selHalf, c.n) : this.flip(this.selFull, c.n); }
                        else if (c.s === 'half' && !c.mine) { this.flip(this.selHalf, c.n); }
                        window.luckyHaptic && window.luckyHaptic('selection');
                    },
                    cellClass(c) {
                        if (this.isSel(c)) return 'cell cell-selected';
                        if (c.s === 'free') return 'cell cell-free';
                        if (c.s === 'half') return c.mine ? 'cell cell-mine' : 'cell cell-half';
                        return c.mine ? 'cell cell-mine' : 'cell cell-sold';
                    },
                    cellLabel(c) {
                        const state = c.s === 'free' ? '{{ __('free') }}'
                            : (c.s === 'half' ? (c.mine ? '{{ __('your half') }}' : '{{ __('half open') }}')
                            : (c.mine ? '{{ __('yours') }}' : '{{ __('sold') }}'));
                        return '{{ __('Number') }} ' + c.n + ' — ' + state;
                    },
                    confirm() { $wire.buy(this.selFull, this.selHalf); },
                    init() { Livewire.on('cleared', () => { this.selFull = []; this.selHalf = []; }); }
                 }">

            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold">{{ __("Pick your numbers") }}</h3>
                @if ($round->allow_half_tickets)
                    <div class="flex rounded-lg bg-white/5 p-0.5 text-xs">
                        <button type="button" @click="mode='full'" :class="mode==='full' ? 'bg-gold-500 text-ink-900' : 'text-slate-300'" class="rounded-md px-3 py-1 font-semibold transition">{{ __('Full') }}</button>
                        <button type="button" @click="mode='half'" :class="mode==='half' ? 'bg-gold-500 text-ink-900' : 'text-slate-300'" class="rounded-md px-3 py-1 font-semibold transition">½ {{ __('Half') }}</button>
                    </div>
                @endif
            </div>

            <div class="mb-2 flex flex-wrap gap-3 text-[10px] text-slate-400">
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-400" aria-hidden="true"></span>{{ __('free') }}</span>
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400" aria-hidden="true"></span>{{ __('half open') }}</span>
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-400" aria-hidden="true"></span>{{ __('sold') }}</span>
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-gold-400" aria-hidden="true"></span>{{ __('yours') }}</span>
            </div>

            <p x-show="inspectMsg" x-text="inspectMsg" class="mb-2 rounded-lg bg-white/5 px-3 py-1.5 text-xs text-slate-300"></p>

            {{-- Skeleton while the board loads --}}
            <div x-show="!board.length" class="grid grid-cols-6 gap-1.5 sm:grid-cols-8">
                <template x-for="i in 18" :key="i"><div class="cell skeleton"></div></template>
            </div>
            <div x-show="board.length" class="grid grid-cols-6 gap-1.5 sm:grid-cols-8" role="group" aria-label="{{ __('Pick your numbers') }}">
                <template x-for="c in board" :key="c.n">
                    <button type="button" @click="tap(c)" :class="cellClass(c)" :aria-label="cellLabel(c)" :aria-pressed="isSel(c)">
                        <span x-text="c.n"></span><span x-show="selHalfQ(c) || (c.s==='half')" class="ml-0.5 text-[9px]" aria-hidden="true">½</span>
                    </button>
                </template>
            </div>

            {{-- Selection summary + buy --}}
            <div class="mt-4 rounded-xl border border-white/10 p-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-400">{{ __('Selected') }}</span>
                    <span class="font-semibold"><span x-text="units"></span> {{ __('ticket(s)') }}</span>
                </div>
                <div class="mt-1 flex items-center justify-between text-sm">
                    <span class="text-slate-400">{{ __('Total') }}</span>
                    <span class="font-bold text-gold-300"><span x-text="cost"></span> {{ $round->currency }}</span>
                </div>

                @if ($player)
                    <p class="mt-2 text-xs text-slate-500">{{ __('Buying as') }} <span class="text-slate-300">{{ $player->name }}</span>
                        · {{ __('Balance') }} <span class="text-gold-300" x-text="balance.toFixed(2)"></span> {{ $round->currency }}</p>
                @endif

                {{-- Enough balance → buy. Otherwise → top up. --}}
                <button type="button" x-show="canAfford" @click="confirm()" :disabled="units < 0.5"
                        wire:loading.attr="disabled" wire:target="buy"
                        class="btn-gold mt-3 w-full text-base">
                    <span wire:loading.remove wire:target="buy">{{ __('Buy') }} <span x-text="units"></span> {{ __('ticket(s)') }} · <span x-text="cost"></span> {{ $round->currency }}</span>
                    <span wire:loading wire:target="buy">{{ __('Processing…') }}</span>
                </button>
                <a x-show="!canAfford" x-cloak href="{{ route('wallet') }}" wire:navigate
                   class="btn-ghost mt-3 w-full text-amber-300">
                    💳 {{ __('Top up wallet to buy') }} (<span x-text="(costNum - balance).toFixed(2)"></span> {{ $round->currency }} {{ __('more') }})
                </a>
            </div>
        </section>

        {{-- Referral --}}
        @if ($player)
            <section class="card mt-4 p-4 text-center">
                <h3 class="font-semibold">👥 {{ __('Invite & earn free tickets') }}</h3>
                <p class="mt-1 text-xs text-slate-400">
                    {{ __(':count invited', ['count' => $player->referral_count]) }} ·
                    <span class="text-gold-300">{{ __(':count free ticket(s)', ['count' => $player->free_tickets]) }}</span>
                </p>
                <button type="button" x-data
                        x-on:click="window.luckyShare('Join me on LuckyDraw and win the prize pool! 🎰', '{{ $player->referralLink((string) config('lottery.bot_username')) }}')"
                        class="btn-ghost mt-3 w-full">📤 {{ __('Share my invite link') }}</button>
            </section>
        @endif
    @endif

    {{-- Post-purchase share --}}
    <button type="button" x-show="share" x-cloak
            x-on:click="window.luckyShare('I just grabbed tickets ' + share.numbers + ' in ' + share.title + '! Join me 🎰', share.shareUrl)"
            class="btn-gold mt-4 w-full">📤 {{ __('Share my tickets') }}</button>
</div>
