@php use App\Enums\TransactionStatus; @endphp
<div x-data="{ tab: 'deposit', toast: null,
        init(){ Livewire.on('toast', (e) => { this.toast = (Array.isArray(e)?e[0]:e).message; clearTimeout(this._t); this._t=setTimeout(()=>this.toast=null,4000); }); } }">

    <header class="mb-4">
        <h1 class="text-2xl font-black gold-text">👛 Wallet</h1>
        <p class="text-xs text-slate-400">Top up, play, and cash out.</p>
    </header>

    <div x-show="toast" x-transition x-cloak
         class="card mb-3 border-gold-500/40 p-3 text-center text-sm font-semibold text-gold-300" x-text="toast"></div>

    @if ($player === null)
        <div class="card p-8 text-center text-sm text-slate-400">Open this app from Telegram to use your wallet.</div>
    @else
        {{-- Balance --}}
        <section class="card p-5 text-center">
            <p class="text-xs uppercase tracking-widest text-slate-400">{{ __('Balance') }}</p>
            <p class="text-4xl font-black tabular-nums gold-text" x-data="counter({{ $balance }})" x-text="display">{{ number_format($balance, 2) }}</p>
            <p class="text-xs text-slate-400">{{ $currency }}</p>
        </section>

        {{-- Tabs --}}
        <div class="mt-4 flex rounded-xl bg-white/5 p-1 text-sm">
            <button @click="tab='deposit'" :class="tab==='deposit' ? 'bg-gold-500 text-ink-900' : 'text-slate-300'" class="flex-1 rounded-lg py-2 font-semibold transition">⬇️ Deposit</button>
            <button @click="tab='withdraw'" :class="tab==='withdraw' ? 'bg-gold-500 text-ink-900' : 'text-slate-300'" class="flex-1 rounded-lg py-2 font-semibold transition">⬆️ Withdraw</button>
        </div>

        {{-- Deposit --}}
        <section x-show="tab==='deposit'" x-transition class="card mt-3 p-4">
            @unless ($verifyReady)
                <p class="mb-3 rounded-lg bg-amber-500/15 p-2 text-xs text-amber-300">⚠️ Payment verification isn't configured yet (set <code>VERIFY_API_KEY</code>).</p>
            @endunless

            <p class="mb-3 text-xs text-slate-400">{{ $instructions }}</p>

            <label class="label">Payment method</label>
            <select wire:model.live="provider" class="input mb-3">
                @foreach ($providers as $p)
                    <option value="{{ $p }}">{{ strtoupper($p) }}</option>
                @endforeach
            </select>

            <label class="label">Transaction reference</label>
            <input type="text" wire:model="reference" class="input" placeholder="e.g. FT253089F68Z">

            @if ($provider === 'cbe')
                <label class="label mt-3">Account suffix (last digits)</label>
                <input type="text" wire:model="suffix" class="input" placeholder="e.g. 16825193">
            @elseif (in_array($provider, ['cbebirr', 'mpesa']))
                <label class="label mt-3">Your phone number</label>
                <input type="tel" wire:model="payerPhone" class="input" placeholder="+2519XXXXXXXX">
            @endif

            <button wire:click="deposit" wire:loading.attr="disabled" wire:target="deposit" class="btn-gold mt-4 w-full">
                <span wire:loading.remove wire:target="deposit">Verify & deposit</span>
                <span wire:loading wire:target="deposit">Verifying…</span>
            </button>
            <p class="mt-2 text-center text-[11px] text-slate-500">Min {{ $minDeposit }} {{ $currency }} · we verify the payment automatically.</p>
        </section>

        {{-- Withdraw --}}
        <section x-show="tab==='withdraw'" x-transition x-cloak class="card mt-3 p-4">
            <label class="label">Amount</label>
            <input type="number" step="0.01" wire:model="amount" class="input" placeholder="0.00">

            <label class="label mt-3">Payout method</label>
            <select wire:model="payoutProvider" class="input mb-3">
                @foreach ($providers as $p)
                    <option value="{{ $p }}">{{ strtoupper($p) }}</option>
                @endforeach
            </select>

            <label class="label">Send to (your phone / account)</label>
            <input type="text" wire:model="payoutAccount" class="input" placeholder="+2519XXXXXXXX">

            <button wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw" class="btn-ghost mt-4 w-full">
                Request withdrawal
            </button>
            <p class="mt-2 text-center text-[11px] text-slate-500">Min {{ $minWithdraw }} {{ $currency }} · paid out by the organiser, then confirmed here.</p>
        </section>

        {{-- History --}}
        <section class="card mt-4 p-4">
            <h3 class="mb-2 font-semibold">Recent activity</h3>
            <div class="space-y-1.5">
                @forelse ($transactions as $t)
                    <div class="flex items-center justify-between border-b border-white/5 py-1.5 text-sm last:border-0">
                        <div class="flex items-center gap-2">
                            <span>{{ $t->type->icon() }}</span>
                            <div>
                                <p class="text-slate-200">{{ $t->type->label() }}</p>
                                <p class="text-[10px] {{ $t->status->color() }}">{{ $t->status->label() }} · {{ $t->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <span class="font-bold {{ $t->signedAmount() >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $t->signedAmount() >= 0 ? '+' : '−' }}{{ number_format(abs($t->signedAmount()), 2) }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No transactions yet.</p>
                @endforelse
            </div>
        </section>
    @endif
</div>
