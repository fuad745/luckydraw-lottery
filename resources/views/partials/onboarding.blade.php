@php
    $slides = [
        ['🎰', __('Welcome to LuckyDraw'), __('Top up your wallet, then tap numbers on the board to buy tickets.')],
        ['🤝', __('Full or half tickets'), __('Buy a full ticket or share a number 50/50 with a friend.')],
        ['🏆', __('Auto draw & payouts'), __('When tickets sell out, the draw runs automatically and winners are paid to their wallet.')],
    ];
@endphp

<div x-data="{
        step: 0,
        total: {{ count($slides) }},
        show: false,
        init() { this.show = localStorage.getItem('lucky_onboarded') !== '1'; },
        done() { localStorage.setItem('lucky_onboarded', '1'); this.show = false; },
        next() { this.step < this.total - 1 ? this.step++ : this.done(); }
     }"
     x-show="show" x-cloak x-transition.opacity
     class="fixed inset-0 z-[60] flex items-end justify-center bg-black/70 backdrop-blur-sm sm:items-center">

    <div class="card m-4 w-full max-w-sm p-6 text-center" x-transition>
        @foreach ($slides as $i => [$emoji, $heading, $body])
            <div x-show="step === {{ $i }}" x-transition>
                <div class="text-5xl">{{ $emoji }}</div>
                <h2 class="mt-4 text-xl font-black gold-text">{{ $heading }}</h2>
                <p class="mt-2 text-sm text-slate-300">{{ $body }}</p>
            </div>
        @endforeach

        {{-- Dots --}}
        <div class="mt-6 flex justify-center gap-1.5">
            @foreach ($slides as $i => $s)
                <span :class="step === {{ $i }} ? 'w-5 bg-gold-500' : 'w-1.5 bg-white/20'" class="h-1.5 rounded-full transition-all"></span>
            @endforeach
        </div>

        <button @click="next()" class="btn-gold mt-6 w-full"
                x-text="step < total - 1 ? '{{ __('Next') }}' : '{{ __('Get started') }}'"></button>
        <button @click="done()" class="mt-2 w-full py-2 text-xs text-slate-400" x-show="step < total - 1">{{ __('Skip') }}</button>
    </div>
</div>
