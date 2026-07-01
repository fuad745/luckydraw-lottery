@php use App\Enums\TransactionStatus; @endphp
<div wire:poll.10s.visible
     x-data="{ tab: 'deposit', toast: null, toastType: 'info',
        init(){ Livewire.on('toast', (e) => {
            const d = Array.isArray(e) ? e[0] : e;
            this.toast = d.message;
            this.toastType = d.type || 'info';
            clearTimeout(this._t);
            this._t = setTimeout(() => this.toast = null, this.toastType === 'error' ? 6000 : 4000);
        }); } }">

    <header class="mb-4">
        <h1 class="text-2xl font-black gold-text">👛 {{ __('Wallet') }}</h1>
        <p class="text-xs text-slate-400">{{ __('Top up, play, and cash out.') }}</p>
    </header>

    {{-- Toast (tap to dismiss; colour + icon distinguish success / error) --}}
    <div x-show="toast" x-transition x-cloak role="status" aria-live="polite"
         @click="toast = null"
         class="card mb-3 flex cursor-pointer items-start gap-2 p-3 text-sm font-semibold"
         :class="toastType === 'error' ? 'border-rose-500/50 text-rose-200' : (toastType === 'success' ? 'border-emerald-500/50 text-emerald-200' : 'border-gold-500/40 text-gold-300')">
        <span aria-hidden="true" x-text="toastType === 'error' ? '⚠️' : (toastType === 'success' ? '✅' : 'ℹ️')"></span>
        <span class="flex-1 text-left" x-text="toast"></span>
    </div>

    @if ($player === null)
        <div class="card p-8 text-center text-sm text-slate-400">{{ __('Open this app from Telegram to use your wallet.') }}</div>
    @else
        {{-- Balance --}}
        <section class="card p-5 text-center">
            <p class="text-xs uppercase tracking-widest text-slate-400">{{ __('Balance') }}</p>
            <p class="text-4xl font-black tabular-nums gold-text" x-data="counter({{ $balance }})" x-text="display">{{ number_format($balance, 2) }}</p>
            <p class="text-xs text-slate-400">{{ $currency }}</p>
        </section>

        {{-- Tabs --}}
        <div class="mt-4 flex rounded-xl bg-white/5 p-1 text-sm" role="tablist">
            <button @click="tab='deposit'" :class="tab==='deposit' ? 'bg-gold-500 text-ink-900' : 'text-slate-300'" :aria-selected="tab==='deposit'" role="tab" class="flex-1 rounded-lg py-2 font-semibold transition">⬇️ {{ __('Deposit') }}</button>
            <button @click="tab='withdraw'" :class="tab==='withdraw' ? 'bg-gold-500 text-ink-900' : 'text-slate-300'" :aria-selected="tab==='withdraw'" role="tab" class="flex-1 rounded-lg py-2 font-semibold transition">⬆️ {{ __('Withdraw') }}</button>
        </div>

        {{-- Deposit --}}
        <section x-show="tab==='deposit'" x-cloak x-transition class="card mt-3 p-4">
            @unless ($verifyReady)
                <p class="mb-3 rounded-lg bg-amber-500/15 p-2 text-xs text-amber-300">⚠️ {{ __("Payment verification isn't configured yet (set :key).", ['key' => 'VERIFY_API_KEY']) }}</p>
            @endunless

            <p class="mb-3 text-xs text-slate-400">{{ $instructions }}</p>

            <label class="label" for="dep-provider">{{ __('Payment method') }}</label>
            <select id="dep-provider" wire:model.live="provider" class="input mb-3">
                @foreach ($providers as $p)
                    <option value="{{ $p }}">{{ strtoupper($p) }}</option>
                @endforeach
            </select>

            <label class="label" for="dep-reference">{{ __('Transaction SMS, link, or number') }}</label>
            <textarea id="dep-reference" rows="3" wire:model="reference" class="input resize-none"
                      placeholder="{{ __('Paste your full payment SMS or receipt link here — or just the transaction number.') }}"></textarea>
            <p class="mt-1 text-[11px] text-slate-500">{{ __('Tip: copy the whole confirmation message from :sender — we pull out the transaction number automatically.', ['sender' => '127 / CBE / M-PESA']) }}</p>

            @if ($provider === 'cbe')
                <label class="label mt-3" for="dep-cbe-account">{{ __('Your CBE account number') }}</label>
                <input id="dep-cbe-account" type="text" inputmode="numeric" wire:model="cbeAccount" class="input" placeholder="{{ __('e.g. 1000230846522') }}">
                <p class="mt-1 text-[11px] text-slate-500">{{ __('The account you paid from — we use it to find your CBE receipt. (Skip if you pasted your full CBE SMS above.)') }}</p>
            @elseif (in_array($provider, ['cbebirr', 'mpesa']))
                <label class="label mt-3" for="dep-phone">{{ __('Your phone number — optional') }}</label>
                <input id="dep-phone" type="tel" wire:model="payerPhone" class="input" placeholder="{{ __('Auto-filled from your SMS if present') }}">
            @endif

            <button wire:click="deposit" wire:loading.attr="disabled" wire:target="deposit" @unless ($verifyReady) disabled @endunless class="btn-gold mt-4 w-full disabled:opacity-50">
                <span wire:loading.remove wire:target="deposit">{{ __('Verify & deposit') }}</span>
                <span wire:loading wire:target="deposit">{{ __('Verifying…') }}</span>
            </button>
            <p class="mt-2 text-center text-[11px] text-slate-500">{{ __('Min :min :currency · we verify the payment automatically.', ['min' => $minDeposit, 'currency' => $currency]) }}</p>
        </section>

        {{-- Withdraw --}}
        <section x-show="tab==='withdraw'" x-transition x-cloak class="card mt-3 p-4">
            <label class="label" for="wd-amount">{{ __('Amount') }}</label>
            <input id="wd-amount" type="number" step="0.01" min="{{ $minWithdraw }}" max="{{ $balance }}" inputmode="decimal" wire:model="amount" class="input" placeholder="0.00">

            <label class="label mt-3" for="wd-provider">{{ __('Payout method') }}</label>
            <select id="wd-provider" wire:model="payoutProvider" class="input mb-3">
                @foreach ($providers as $p)
                    <option value="{{ $p }}">{{ strtoupper($p) }}</option>
                @endforeach
            </select>

            <label class="label" for="wd-account">{{ __('Send to (your phone / account)') }}</label>
            <input id="wd-account" type="text" wire:model="payoutAccount" class="input" placeholder="+2519XXXXXXXX">

            <button wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw" class="btn-ghost mt-4 w-full">
                <span wire:loading.remove wire:target="withdraw">{{ __('Request withdrawal') }}</span>
                <span wire:loading wire:target="withdraw">{{ __('Requesting…') }}</span>
            </button>
            <p class="mt-2 text-center text-[11px] text-slate-500">{{ __('Min :min :currency · paid out by the organiser, then confirmed here.', ['min' => $minWithdraw, 'currency' => $currency]) }}</p>
        </section>

        {{-- Withdrawal status timeline --}}
        @if ($withdrawals->isNotEmpty())
            <section class="card mt-4 p-4">
                <h3 class="mb-3 font-semibold">{{ __('Your withdrawals') }}</h3>
                <div class="space-y-4">
                    @foreach ($withdrawals as $w)
                        @php $paid = $w->status === TransactionStatus::Completed; $declined = $w->status === TransactionStatus::Rejected; @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-semibold text-slate-200">{{ number_format((float) $w->amount, 2) }} {{ $currency }}</span>
                                <span class="text-[11px] {{ $w->status->color() }}">{{ __($w->status->label()) }}</span>
                            </div>
                            <p class="mb-2 text-[10px] text-slate-500">{{ __('To') }} {{ $w->reference }} · {{ $w->created_at->diffForHumans() }}</p>

                            {{-- Requested → Paid / Declined stepper --}}
                            <div class="flex items-center gap-1.5" aria-hidden="true">
                                <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                <span class="h-0.5 flex-1 rounded {{ $paid ? 'bg-emerald-400' : ($declined ? 'bg-rose-400' : 'bg-amber-400/50') }}"></span>
                                @if ($declined)
                                    <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                                @else
                                    <span class="h-2 w-2 rounded-full {{ $paid ? 'bg-emerald-400' : 'animate-pulse bg-amber-400' }}"></span>
                                @endif
                            </div>
                            <div class="mt-1 flex justify-between text-[10px] text-slate-500">
                                <span>{{ __('Requested') }}</span>
                                <span>{{ $declined ? __('Declined — refunded') : ($paid ? __('Paid') : __('Awaiting payout')) }}{{ $w->processed_at ? ' · '.$w->processed_at->diffForHumans() : '' }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- History --}}
        <section class="card mt-4 p-4">
            <h3 class="mb-2 font-semibold">{{ __('Recent activity') }}</h3>
            <div class="space-y-1.5">
                @forelse ($transactions as $t)
                    <div class="flex items-center justify-between border-b border-white/5 py-1.5 text-sm last:border-0">
                        <div class="flex items-center gap-2">
                            <span aria-hidden="true">{{ $t->type->icon() }}</span>
                            <div>
                                <p class="text-slate-200">{{ __($t->type->label()) }}</p>
                                <p class="text-[10px] {{ $t->status->color() }}">{{ __($t->status->label()) }} · {{ $t->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <span class="font-bold {{ $t->signedAmount() >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $t->signedAmount() >= 0 ? '+' : '−' }}{{ number_format(abs($t->signedAmount()), 2) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No transactions yet.') }}</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
