<div>
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-100">Settings</h1>
        <p class="text-sm text-slate-400">Admin login and payment configuration.</p>
    </div>

    {{-- Payment settings --}}
    <form wire:submit="savePayments" class="card mb-8 max-w-lg space-y-4 p-5">
        <h2 class="text-lg font-bold text-slate-100">💳 Payments</h2>

        <div>
            <label class="label" for="s-verifykey">Verify API key</label>
            <input id="s-verifykey" type="text" wire:model="verifyKey" class="input font-mono text-sm" placeholder="Paste your verifyapi.leulzenebe.pro API key" autocomplete="off">
            <p class="mt-1 text-xs text-slate-500">Get a free key at <a href="https://verify.leul.et" target="_blank" rel="noopener" class="text-gold-300 underline">verify.leul.et</a>. Without it, automatic verification is off and every deposit goes to manual review.</p>
            @error('verifyKey') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>

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
            <div class="mb-1.5 flex items-center justify-between">
                <span class="label mb-0">Deposit accounts (shown to players)</span>
                <button type="button" wire:click="addAccountRow" class="btn-ghost px-3 py-1.5 text-xs">+ Add account</button>
            </div>
            <p class="mb-2 text-xs text-slate-500">Each account is shown as a copyable card on the deposit screen and in the bot's /deposit reply.</p>

            @if (count($accountList) === 0)
                <p class="rounded-xl border border-dashed border-white/10 p-3 text-center text-xs text-slate-500">No accounts yet — players only see the free-text instructions below. Add one 👆</p>
            @endif

            <div class="space-y-2">
                @foreach ($accountList as $i => $row)
                    <div class="rounded-xl border border-white/10 p-3" wire:key="acc-{{ $i }}">
                        <div class="grid grid-cols-[7.5rem_1fr_auto] gap-2">
                            <select wire:model="accountList.{{ $i }}.provider" class="input py-2 text-sm" aria-label="Provider">
                                @foreach ($supportedProviders as $p)
                                    <option value="{{ $p }}">{{ strtoupper($p) }}</option>
                                @endforeach
                            </select>
                            <input type="text" wire:model="accountList.{{ $i }}.number" class="input py-2 text-sm" placeholder="Account / phone number" aria-label="Account number">
                            <button type="button" wire:click="removeAccountRow({{ $i }})" class="btn-ghost px-3 py-2 text-rose-300" aria-label="Remove account">✕</button>
                        </div>
                        <input type="text" wire:model="accountList.{{ $i }}.name" class="input mt-2 py-2 text-sm" placeholder="Account holder name (optional)" aria-label="Account name">
                        @error('accountList.'.$i.'.number') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                        @error('accountList.'.$i.'.provider') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
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
            <x-admin.spinner wire:loading wire:target="savePayments" />
            <span wire:loading.remove wire:target="savePayments">Save payment settings</span>
            <span wire:loading wire:target="savePayments">Saving…</span>
        </button>
    </form>

    {{-- Admin credentials --}}
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

        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-gold w-full sm:w-auto sm:px-8">
            <x-admin.spinner wire:loading wire:target="save" />
            <span wire:loading.remove wire:target="save">Save changes</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </form>
</div>
