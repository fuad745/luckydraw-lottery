<div>
    <div class="mb-6">
        <h1 class="text-2xl font-black text-slate-100">Broadcast</h1>
        <p class="text-sm text-slate-400">Send a Telegram message to your players.</p>
    </div>

    @if ($flash)
        <div class="card mb-4 border-emerald-500/30 p-3 text-sm font-semibold text-emerald-300">{{ $flash }}</div>
    @endif

    <div class="card max-w-2xl p-5">
        <label class="label">Audience</label>
        <div class="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
            @php
                $opts = [
                    'all' => ['All players', $counts['all']],
                    'with_balance' => ['With balance', $counts['with_balance']],
                    'recent_buyers' => ['Have played', $counts['recent_buyers']],
                ];
            @endphp
            @foreach ($opts as $key => [$label, $count])
                <button type="button" wire:click="$set('audience', '{{ $key }}')"
                        class="rounded-xl border p-3 text-left transition {{ $audience === $key ? 'border-gold-500/50 bg-gold-500/10' : 'border-white/10 hover:bg-white/5' }}">
                    <p class="text-sm font-medium text-slate-100">{{ $label }}</p>
                    <p class="text-xs text-slate-400">{{ $count }} player(s)</p>
                </button>
            @endforeach
        </div>

        <label class="label" for="bmsg">Message (HTML allowed: &lt;b&gt; &lt;i&gt;)</label>
        <textarea id="bmsg" wire:model="message" rows="6" class="input" placeholder="🎉 A new round starts in 10 minutes — top up and join!"></textarea>
        @error('message') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror

        <button wire:click="send" wire:confirm="Send this message to the selected players?" wire:loading.attr="disabled"
                class="btn-gold mt-4 w-full sm:w-auto sm:px-8">Send broadcast</button>
        <p class="mt-2 text-xs text-slate-500">Messages are queued and delivered by the worker. Only users who have started the bot receive DMs.</p>
    </div>
</div>
