<div>
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-100">Settings</h1>
        <p class="text-sm text-slate-400">Admin login and payment configuration.</p>
    </div>

    {{-- Payment settings --}}
    @if ($payFlash)
        <div class="card mb-4 border-emerald-500/30 p-3 text-sm font-semibold text-emerald-300">{{ $payFlash }}</div>
    @endif

    <form wire:submit="savePayments" class="card mb-8 max-w-lg space-y-4 p-5">
        <h2 class="text-lg font-bold text-slate-100">💳 Payments</h2>

        <div>
            <label class="label">Payment methods</label>
            <p class="mb-2 text-xs text-slate-500">Enable the methods players can deposit/withdraw with.</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($supportedProviders as $p)
                    <label class="flex items-center gap-2 rounded-xl border border-white/10 p-2.5 text-sm">
                        <input type="checkbox" value="{{ $p }}" wire:model="providers" class="h-5 w-5 accent-[var(--color-gold-500)]">
                        <span class="uppercase">{{ $p }}</span>
                    </label>
                @endforeach
            </div>
            @error('providers') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="s-accounts">Receiving accounts (anti-fraud)</label>
            <textarea id="s-accounts" rows="3" wire:model="depositAccounts" class="input resize-none" placeholder="251925278350, Fuad Ahmed, 1000230846522"></textarea>
            <p class="mt-1 text-xs text-slate-500">Your account name(s) &amp; number(s), comma or line separated. A deposit only counts if its verified receiver matches one of these. Leave blank only in testing.</p>
            @error('depositAccounts') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="label" for="s-mindep">Min deposit</label>
                <input id="s-mindep" type="number" step="0.01" min="0" wire:model="minDeposit" class="input">
                @error('minDeposit') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="s-minwd">Min withdrawal</label>
                <input id="s-minwd" type="number" step="0.01" min="0" wire:model="minWithdraw" class="input">
                @error('minWithdraw') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="label" for="s-instr">Deposit instructions (shown to players)</label>
            <textarea id="s-instr" rows="2" wire:model="depositInstructions" class="input resize-none"></textarea>
            @error('depositInstructions') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:loading.attr="disabled" wire:target="savePayments" class="btn-gold w-full sm:w-auto sm:px-8">
            <span wire:loading.remove wire:target="savePayments">Save payment settings</span>
            <span wire:loading wire:target="savePayments">Saving…</span>
        </button>
    </form>

    {{-- Admin credentials --}}
    @if ($flash)
        <div class="card mb-4 border-emerald-500/30 p-3 text-sm font-semibold text-emerald-300">{{ $flash }}</div>
    @endif

    <form wire:submit="save" class="card max-w-lg space-y-4 p-5">
        <h2 class="text-lg font-bold text-slate-100">🔐 Admin login</h2>

        <div>
            <label class="label" for="s-username">Username</label>
            <input id="s-username" type="text" wire:model="username" autocomplete="username" class="input">
            @error('username') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>

        <hr class="border-white/10">

        <div>
            <label class="label" for="s-newpass">New password <span class="text-slate-500">(leave blank to keep current)</span></label>
            <input id="s-newpass" type="password" wire:model="new_password" autocomplete="new-password" class="input" placeholder="••••••••">
            @error('new_password') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="s-newpass2">Confirm new password</label>
            <input id="s-newpass2" type="password" wire:model="new_password_confirmation" autocomplete="new-password" class="input" placeholder="••••••••">
        </div>

        <hr class="border-white/10">

        <div>
            <label class="label" for="s-curpass">Current password <span class="text-rose-400">*</span></label>
            <input id="s-curpass" type="password" wire:model="current_password" autocomplete="current-password" class="input" placeholder="Enter your current password to confirm">
            @error('current_password') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">Required to save any change.</p>
        </div>

        <button type="submit" wire:loading.attr="disabled" class="btn-gold w-full sm:w-auto sm:px-8">
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </form>
</div>
