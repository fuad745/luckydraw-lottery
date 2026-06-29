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
</body>
</html>
