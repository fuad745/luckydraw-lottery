<div>
    <header class="mb-4">
        <h1 class="text-2xl font-black gold-text">{{ __('Settings') }}</h1>
        <p class="text-xs text-slate-400">{{ __('Your preferences') }}</p>
    </header>

    {{-- Language --}}
    <section class="card p-4">
        <h2 class="mb-3 font-semibold">{{ __('Language') }}</h2>
        <div class="grid grid-cols-2 gap-2">
            <button wire:click="setLanguage('en')"
                    class="rounded-xl border p-3 text-center transition {{ $locale === 'en' ? 'border-gold-500/50 bg-gold-500/10 text-gold-300' : 'border-white/10 text-slate-300 hover:bg-white/5' }}">
                <span class="text-lg">🇬🇧</span>
                <p class="text-sm font-medium">English</p>
            </button>
            <button wire:click="setLanguage('am')"
                    class="rounded-xl border p-3 text-center transition {{ $locale === 'am' ? 'border-gold-500/50 bg-gold-500/10 text-gold-300' : 'border-white/10 text-slate-300 hover:bg-white/5' }}">
                <span class="text-lg">🇪🇹</span>
                <p class="text-sm font-medium">አማርኛ</p>
            </button>
        </div>
    </section>

    {{-- Sound (client preference) --}}
    <section class="card mt-4 p-4"
             x-data="{ sound: localStorage.getItem('lucky_sound') !== 'off' }"
             x-init="$watch('sound', v => localStorage.setItem('lucky_sound', v ? 'on' : 'off'))">
        <label class="flex items-center justify-between">
            <span class="text-sm font-medium">{{ __('Sound effects') }}</span>
            <button type="button" @click="sound = !sound"
                    :class="sound ? 'bg-gold-500' : 'bg-white/15'"
                    class="relative h-6 w-11 rounded-full transition" role="switch" :aria-checked="sound.toString()">
                <span :class="sound ? 'translate-x-5' : 'translate-x-0.5'"
                      class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition"></span>
            </button>
        </label>
    </section>

    <p class="mt-6 text-center text-[11px] text-slate-600">LuckyDraw 🎰</p>
</div>
