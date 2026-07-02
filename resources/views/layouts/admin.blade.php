<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0e14">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} · LuckyDraw</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-ink-900 text-slate-100" x-data="{ open: false }">

    @php
        $nav = [
            ['admin', 'dashboard', 'Dashboard'],
            ['admin.rounds', 'rounds', 'Rounds'],
            ['admin.players', 'players', 'Players'],
            ['admin.withdrawals', 'withdrawals', 'Withdrawals'],
            ['admin.transactions', 'transactions', 'Transactions'],
            ['admin.broadcast', 'broadcast', 'Broadcast'],
            ['admin.settings', 'settings', 'Settings'],
        ];
        $pendingWithdrawals = \App\Models\Transaction::where('type', 'withdrawal')->where('status', 'pending')->count();
    @endphp

    {{-- Mobile top bar --}}
    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-white/5 bg-ink-850/95 px-4 py-3 backdrop-blur lg:hidden">
        <button @click="open = true" class="rounded-lg p-2 text-slate-300 hover:bg-white/5" aria-label="Open menu">
            <x-admin.icon name="menu" />
        </button>
        <span class="text-lg font-black gold-text">LuckyDraw Admin</span>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf
            <button class="rounded-lg p-2 text-slate-300 hover:bg-white/5" aria-label="Log out"><x-admin.icon name="logout" /></button>
        </form>
    </header>

    {{-- Mobile drawer scrim --}}
    <div x-show="open" x-transition.opacity @click="open = false" x-cloak
         class="fixed inset-0 z-40 bg-black/60 lg:hidden"></div>

    {{-- Sidebar (desktop fixed / mobile drawer) --}}
    <aside x-cloak
           class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-white/5 bg-ink-850 transition-transform lg:translate-x-0"
           :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex items-center justify-between px-5 py-5">
            <a href="{{ route('admin') }}" class="text-xl font-black gold-text">LuckyDraw</a>
            <button @click="open = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-white/5 lg:hidden" aria-label="Close menu">
                <x-admin.icon name="close" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 px-3">
            @foreach ($nav as [$route, $icon, $label])
                @php $active = request()->routeIs($route); @endphp
                <a href="{{ route($route) }}" @click="open = false"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-gold-500/15 text-gold-300 ring-1 ring-gold-500/30' : 'text-slate-300 hover:bg-white/5' }}">
                    <x-admin.icon :name="$icon" class="h-5 w-5 shrink-0" />
                    <span>{{ $label }}</span>
                    @if ($route === 'admin.withdrawals' && $pendingWithdrawals > 0)
                        <span class="ml-auto rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $pendingWithdrawals }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="border-t border-white/5 p-3">
            <a href="{{ route('home') }}" target="_blank" class="mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-400 hover:bg-white/5">
                <x-admin.icon name="home" class="h-5 w-5" /> View game
            </a>
            <form method="POST" action="{{ route('admin.logout') }}">@csrf
                <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-rose-300 hover:bg-rose-500/10">
                    <x-admin.icon name="logout" class="h-5 w-5" /> Log out
                </button>
            </form>
        </div>
    </aside>

    {{-- Main content --}}
    <main class="lg:pl-64">
        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
    </main>

    {{-- Global toast host: listens for Livewire `toast` events from any
         component, plus a one-shot session flash (post-redirect feedback). --}}
    <div x-data="{
            toasts: [],
            add(t) {
                const id = ++this._id;
                this.toasts.push({ id, message: t.message, type: t.type || 'info' });
                setTimeout(() => this.remove(id), t.type === 'error' ? 6000 : 4000);
            },
            remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
            _id: 0,
            init() {
                Livewire.on('toast', (e) => this.add(Array.isArray(e) ? e[0] : e));
                @if (session('admin_toast')) this.add(@js(session('admin_toast'))); @endif
            }
         }"
         class="pointer-events-none fixed inset-x-0 top-4 z-[70] flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6"
         role="status" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div @click="remove(t.id)" x-transition
                 class="pointer-events-auto card flex w-full max-w-sm cursor-pointer items-start gap-2.5 p-3.5 text-sm font-semibold"
                 :class="t.type === 'error' ? 'border-rose-500/50 text-rose-200'
                        : (t.type === 'success' ? 'border-emerald-500/50 text-emerald-200' : 'border-gold-500/40 text-gold-300')">
                <svg x-show="t.type === 'success'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                <svg x-show="t.type === 'error'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                <svg x-show="t.type !== 'success' && t.type !== 'error'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                <span class="flex-1" x-text="t.message"></span>
            </div>
        </template>
    </div>
</body>
</html>
