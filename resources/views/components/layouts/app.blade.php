<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="dark"
      data-dev="{{ app()->environment('local') ? '1' : '0' }}">
<head>
    <meta charset="utf-8">
    {{-- Allow pinch-zoom (a11y) — Mini Apps don't need to lock scale. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0e14">
    <title>{{ $title ?? 'LuckyDraw 🎰' }}</title>

    {{-- Telegram Mini App SDK (must load from telegram.org, not bundled) --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    {{-- Gate: the game runs ONLY inside the Telegram Mini App. This runs before
         first paint, so a plain browser never sees the game (admin uses a
         different layout and is unaffected). Local dev (data-dev=1) bypasses. --}}
    <script>
        (function () {
            var html = document.documentElement;
            var dev = html.dataset.dev === '1';
            var wa = window.Telegram && window.Telegram.WebApp;
            var inTelegram = !!(wa && wa.initData && wa.initData.length > 0);
            html.classList.add(dev || inTelegram ? 'tg-ok' : 'tg-blocked');
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    {{-- Shown only when opened outside Telegram --}}
    <div class="tg-gate">
        <div class="mx-auto max-w-xs px-6 text-center">
            <div class="text-6xl">🎰</div>
            <h1 class="mt-4 text-2xl font-black gold-text">LuckyDraw</h1>
            <p class="mt-2 text-sm text-slate-400">{{ __('This game runs inside Telegram. Open it from the bot to play.') }}</p>
            <a href="https://t.me/{{ config('lottery.bot_username') }}"
               class="btn-gold mt-6 w-full">{{ __('Open in Telegram') }}</a>
        </div>
    </div>

    <div class="app-shell mx-auto flex min-h-screen w-full max-w-md flex-col px-4 pb-24 pt-4">
        {{ $slot }}
    </div>

    {{-- First-run onboarding --}}
    @include('partials.onboarding')

    {{-- Bottom navigation --}}
    <nav class="app-nav fixed inset-x-0 bottom-0 z-40 border-t border-white/5 bg-ink-850/95 backdrop-blur">
        <div class="mx-auto grid max-w-md grid-cols-5 text-center text-[11px]">
            @php
                $nav = [
                    ['home', '🎰', __('Play')],
                    ['wallet', '👛', __('Wallet')],
                    ['my-tickets', '🎫', __('Tickets')],
                    ['history', '📜', __('History')],
                    ['leaderboard', '🏆', __('Winners')],
                ];
            @endphp
            @foreach ($nav as [$route, $icon, $label])
                <a href="{{ route($route) }}" wire:navigate
                   class="flex flex-col items-center gap-0.5 py-2.5 {{ request()->routeIs($route) ? 'text-gold-400' : 'text-slate-400' }}">
                    <span class="text-lg" aria-hidden="true">{{ $icon }}</span>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </nav>
</body>
</html>
