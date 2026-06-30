<div x-data="{ toast: null, toastType: 'info',
        init(){ Livewire.on('toast', (e) => {
            const d = Array.isArray(e) ? e[0] : e;
            this.toast = d.message; this.toastType = d.type || 'info';
            clearTimeout(this._t); this._t = setTimeout(() => this.toast = null, this.toastType === 'error' ? 6000 : 4000);
        }); },
        updateContact(){ window.luckyRequestContact((phone) => { if (phone) $wire.updatePhone(phone); }); } }">
    <header class="mb-4">
        <h1 class="text-2xl font-black gold-text">{{ __('Settings') }}</h1>
        <p class="text-xs text-slate-400">{{ __('Your preferences') }}</p>
    </header>

    {{-- Toast --}}
    <div x-show="toast" x-transition x-cloak role="status" aria-live="polite"
         @click="toast = null"
         class="card mb-3 flex cursor-pointer items-start gap-2 p-3 text-sm font-semibold"
         :class="toastType === 'error' ? 'border-rose-500/50 text-rose-200' : (toastType === 'success' ? 'border-emerald-500/50 text-emerald-200' : 'border-gold-500/40 text-gold-300')">
        <span aria-hidden="true" x-text="toastType === 'error' ? '⚠️' : (toastType === 'success' ? '✅' : 'ℹ️')"></span>
        <span class="flex-1 text-left" x-text="toast"></span>
    </div>

    {{-- Profile --}}
    @if ($player)
        <section class="card mb-4 p-4">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gold-500/15 text-xl font-black text-gold-300">
                    {{ mb_strtoupper(mb_substr($player->name ?: 'P', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-bold text-slate-100">{{ $player->name }}</p>
                    <p class="truncate text-xs text-slate-400">{{ $player->username ? '@'.$player->username : __('No username') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-black tabular-nums gold-text">{{ number_format((float) $player->balance, 2) }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500">{{ config('lottery.currency', 'ETB') }}</p>
                </div>
            </div>

            <dl class="mt-3 space-y-1.5 border-t border-white/5 pt-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('Phone') }}</dt>
                    <dd class="flex items-center gap-2">
                        <span class="tabular-nums text-slate-200">{{ $player->phone ?: '—' }}</span>
                        <button type="button" @click="updateContact()" class="text-xs text-gold-400 underline focus-ring">{{ $player->phone ? __('Change') : __('Add') }}</button>
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('Referral code') }}</dt>
                    <dd class="font-mono text-slate-200">{{ $player->referral_code }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('Invited') }}</dt>
                    <dd class="text-slate-200">{{ __(':count friends', ['count' => (int) $player->referral_count]) }} · {{ __(':count free', ['count' => (int) $player->free_tickets]) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-400">{{ __('Joined') }}</dt>
                    <dd class="text-slate-200">{{ $player->created_at?->format('M j, Y') }}</dd>
                </div>
            </dl>
        </section>
    @endif

    {{-- Language --}}
    <section class="card p-4">
        <h2 class="mb-3 font-semibold">{{ __('Language') }}</h2>
        <div class="grid grid-cols-2 gap-2">
            <button wire:click="setLanguage('en')"
                    class="rounded-xl border p-3 text-center transition focus-ring {{ $locale === 'en' ? 'border-gold-500/50 bg-gold-500/10 text-gold-300' : 'border-white/10 text-slate-300 hover:bg-white/5' }}">
                <span class="text-lg" aria-hidden="true">🇬🇧</span>
                <p class="text-sm font-medium">English</p>
            </button>
            <button wire:click="setLanguage('am')"
                    class="rounded-xl border p-3 text-center transition focus-ring {{ $locale === 'am' ? 'border-gold-500/50 bg-gold-500/10 text-gold-300' : 'border-white/10 text-slate-300 hover:bg-white/5' }}">
                <span class="text-lg" aria-hidden="true">🇪🇹</span>
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
                    class="relative h-6 w-11 rounded-full transition focus-ring" role="switch" :aria-checked="sound.toString()">
                <span :class="sound ? 'translate-x-5' : 'translate-x-0.5'"
                      class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition"></span>
            </button>
        </label>
    </section>

    <p class="mt-6 text-center text-[11px] text-slate-600">LuckyDraw 🎰 · v{{ config('app.version', '1.0') }}</p>
</div>
