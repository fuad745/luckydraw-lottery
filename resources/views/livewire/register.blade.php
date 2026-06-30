<div wire:poll.4s="checkRegistered"
     x-data="{ toast: null, toastType: 'info', sharing: false,
        init(){ Livewire.on('toast', (e) => {
            const d = Array.isArray(e) ? e[0] : e;
            this.toast = d.message; this.toastType = d.type || 'info';
            clearTimeout(this._t); this._t = setTimeout(() => this.toast = null, 6000);
        }); },
        share(){
            this.sharing = true;
            window.luckyRequestContact((phone) => {
                if (phone) { $wire.savePhone(phone); }
                else { this.sharing = false; }
            });
        } }">

    {{-- Toast --}}
    <div x-show="toast" x-transition x-cloak role="status" aria-live="polite" @click="toast = null"
         class="card mb-3 flex cursor-pointer items-start gap-2 p-3 text-sm font-semibold border-rose-500/50 text-rose-200">
        <span aria-hidden="true">⚠️</span><span class="flex-1 text-left" x-text="toast"></span>
    </div>

    <div class="flex min-h-[80vh] flex-col items-center justify-center text-center">
        <div class="text-6xl">🎟️</div>
        <h1 class="mt-4 text-2xl font-black gold-text">
            {{ $name ? __('Welcome, :name!', ['name' => $name]) : __('Welcome to LuckyDraw!') }}
        </h1>
        <p class="mt-2 max-w-xs text-sm text-slate-400">
            {{ __('Share your phone number once to create your account. We use it to pay out your winnings and withdrawals.') }}
        </p>

        <button type="button" @click="share()" :disabled="sharing"
                wire:loading.attr="disabled" wire:target="savePhone"
                class="btn-gold mt-8 w-full max-w-xs disabled:opacity-60">
            <span x-show="!sharing">📱 {{ __('Share my contact to start') }}</span>
            <span x-show="sharing">{{ __('Waiting for Telegram…') }}</span>
        </button>

        <p class="mt-3 max-w-xs text-[11px] text-slate-600">
            {{ __('Your number is private — only the organiser can see it, never other players.') }}
        </p>
    </div>
</div>
