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
            // Telegram's in-app browser/webview WITHOUT Mini App context: an
            // "Open in Telegram" link is useless there (we're already in
            // Telegram) — flag it so the gate shows guidance instead of the button.
            var tgWebview = !!window.TelegramWebviewProxy || /telegram/i.test(navigator.userAgent);
            if (!inTelegram && tgWebview) html.classList.add('tg-inapp');
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
            {{-- Outside Telegram entirely → deep-link to the bot. --}}
            <a href="https://t.me/{{ config('lottery.bot_username') }}"
               class="tg-open-link btn-gold mt-6 w-full">{{ __('Open in Telegram') }}</a>
            {{-- Already inside Telegram's webview (no Mini App context) → the
                 link would go nowhere useful; guide to the Play button instead. --}}
            <p class="tg-inapp-hint mt-6 rounded-xl border border-gold-500/30 bg-gold-500/10 p-3 text-sm text-gold-300">
                🎟 {{ __('Go back to the bot chat and tap the Play button to launch the game.') }}
            </p>
        </div>
    </div>

    <div class="app-shell mx-auto flex min-h-screen w-full max-w-md flex-col px-4 pb-24 pt-4">
        {{ $slot }}
    </div>

    {{-- First-run onboarding --}}
    @include('partials.onboarding')

    {{-- Bottom navigation (max 5 tabs; History lives on the Profile page) --}}
    <nav class="app-nav fixed inset-x-0 bottom-0 z-40 border-t border-white/5 bg-ink-850/95 pb-[env(safe-area-inset-bottom)] backdrop-blur">
        <div class="mx-auto grid max-w-md grid-cols-5 text-center text-[11px]">
            @php
                // Heroicons outline paths — crisp SVG icons instead of emoji.
                $nav = [
                    ['home', __('Play'), 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z'],
                    ['wallet', __('Wallet'), 'M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3'],
                    ['my-tickets', __('Tickets'), 'M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122'],
                    ['leaderboard', __('Winners'), 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0'],
                    ['settings', __('Profile'), 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
                ];
            @endphp
            @foreach ($nav as [$route, $label, $d])
                @php $active = request()->routeIs($route); @endphp
                <a href="{{ route($route) }}" wire:navigate
                   @if ($active) aria-current="page" @endif
                   class="flex flex-col items-center gap-0.5 py-2.5 transition {{ $active ? 'text-gold-400' : 'text-slate-400 hover:text-slate-200' }}">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="{{ $active ? 2 : 1.6 }}" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}" />
                    </svg>
                    <span class="{{ $active ? 'font-semibold' : '' }}">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </nav>
</body>
</html>
