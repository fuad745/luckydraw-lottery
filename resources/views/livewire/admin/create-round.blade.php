<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-slate-100">New round</h1>
            <p class="text-sm text-slate-400">Configure and launch a lottery round.</p>
        </div>
        <a href="{{ route('admin.rounds') }}" class="btn-ghost px-4 py-2 text-sm">← Back to rounds</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
        <section class="card max-w-2xl space-y-4 p-5">
            <div>
                <label class="label" for="cr-title">Round title <span class="text-rose-400">*</span></label>
                <input id="cr-title" type="text" wire:model="title" class="input" placeholder="Friday Mega Draw">
                @error('title') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="cr-total">Total tickets <span class="text-rose-400">*</span></label>
                    <input id="cr-total" type="number" wire:model.live="totalTickets" class="input" min="2" max="1000" inputmode="numeric">
                    @error('totalTickets') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="cr-price">Ticket price <span class="text-rose-400">*</span></label>
                    <input id="cr-price" type="number" step="0.01" wire:model.live="ticketPrice" class="input" min="1" inputmode="decimal">
                    @error('ticketPrice') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="cr-currency">Currency</label>
                    <input id="cr-currency" type="text" wire:model="currency" class="input" maxlength="8">
                    @error('currency') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="cr-winners">Winners</label>
                    <input id="cr-winners" type="number" wire:model.live="winnersCount" class="input" min="1" max="5" inputmode="numeric">
                    @error('winnersCount') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rounded-xl border border-white/10 p-3">
                <p class="label">Prize split</p>
                <div class="space-y-2">
                    @foreach ($tiers as $i => $tier)
                        <div class="flex items-center gap-2" wire:key="tier-{{ $i }}">
                            <span class="w-8 text-xs text-slate-400">#{{ $i + 1 }}</span>
                            <select wire:model.live="tiers.{{ $i }}.type" class="input flex-1 py-2 text-sm" aria-label="Prize type #{{ $i + 1 }}">
                                <option value="percent">% of pot</option>
                                <option value="ticket_price">1 ticket price</option>
                            </select>
                            @if (($tier['type'] ?? 'percent') === 'percent')
                                <div class="relative w-24">
                                    <input type="number" step="1" min="0" max="100" wire:model.live="tiers.{{ $i }}.value" class="input py-2 pr-6 text-right text-sm" aria-label="Prize percent #{{ $i + 1 }}">
                                    <span class="absolute right-2 top-2.5 text-xs text-slate-500">%</span>
                                </div>
                            @else
                                <span class="w-24 text-right text-xs text-slate-400">{{ $ticketPrice }} {{ $currency }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center justify-between rounded-xl border border-white/10 p-3 text-sm"><span>Half tickets</span><input type="checkbox" wire:model="allowHalf" class="h-5 w-5 accent-[var(--color-gold-500)]"></label>
                <label class="flex items-center justify-between rounded-xl border border-white/10 p-3 text-sm"><span>Auto-draw</span><input type="checkbox" wire:model="autoDraw" class="h-5 w-5 accent-[var(--color-gold-500)]"></label>
            </div>

            <div class="rounded-xl border border-white/10 p-3">
                <label class="flex items-center justify-between text-sm"><span>Auto-start next round</span><input type="checkbox" wire:model.live="autoRestart" class="h-5 w-5 accent-[var(--color-gold-500)]"></label>
                @if ($autoRestart)
                    <div class="mt-2">
                        <label class="label" for="cr-delay">Restart delay (minutes)</label>
                        <input id="cr-delay" type="number" wire:model="restartDelay" class="input" min="1" max="1440" inputmode="numeric">
                        @error('restartDelay') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="cr-deadline">Deadline (optional)</label>
                    <input id="cr-deadline" type="datetime-local" wire:model="deadline" class="input text-sm">
                    @error('deadline') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="cr-channel">Channel (optional)</label>
                    <input id="cr-channel" type="text" wire:model="channelId" class="input text-sm" placeholder="@channel">
                </div>
            </div>

            <button type="button" wire:click="createRound"
                    wire:confirm="Start this round now? It goes live for players immediately."
                    wire:loading.attr="disabled" wire:target="createRound"
                    class="btn-gold w-full text-base">
                <x-admin.spinner wire:loading wire:target="createRound" />
                <span wire:loading.remove wire:target="createRound">🚀 Start round</span>
                <span wire:loading wire:target="createRound">Starting…</span>
            </button>
        </section>

        {{-- Live payout preview --}}
        <aside class="card h-fit p-5 lg:sticky lg:top-6">
            <h2 class="mb-3 font-semibold text-slate-100">Payout preview</h2>
            <p class="mb-2 text-xs text-slate-400">At full sell-out ({{ $totalTickets ?: '—' }} × {{ $ticketPrice ?: '—' }} {{ $currency }}):</p>
            <p class="mb-3 text-2xl font-black tabular-nums text-gold-300">{{ number_format($previewPot, 2) }} <span class="text-sm font-semibold text-slate-400">{{ $currency }}</span></p>
            <div class="space-y-1.5 text-sm">
                @foreach ($previewTiers as $i => $amt)
                    <div class="flex justify-between"><span class="text-slate-400">{{ ['🥇','🥈','🥉'][$i] ?? '🏅' }} Winner #{{ $i + 1 }}</span><span class="tabular-nums text-gold-300">{{ $amt }} {{ $currency }}</span></div>
                @endforeach
                <div class="flex justify-between border-t border-white/10 pt-1.5 text-slate-400"><span>House</span><span class="tabular-nums">{{ $previewAdmin }} {{ $currency }}</span></div>
            </div>
        </aside>
    </div>
</div>
