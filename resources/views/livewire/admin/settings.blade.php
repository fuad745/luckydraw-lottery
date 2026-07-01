<div>
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-100">Settings</h1>
        <p class="text-sm text-slate-400">Change the admin panel username and password.</p>
    </div>

    @if ($flash)
        <div class="card mb-4 border-emerald-500/30 p-3 text-sm font-semibold text-emerald-300">{{ $flash }}</div>
    @endif

    <form wire:submit="save" class="card max-w-lg space-y-4 p-5">
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
