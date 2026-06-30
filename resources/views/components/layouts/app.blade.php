<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="dark">
<head>
    <meta charset="utf-8">
    {{-- Allow pinch-zoom (a11y) — Mini Apps don't need to lock scale. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0e14">
    <title>{{ $title ?? 'LuckyDraw 🎰' }}</title>

    {{-- Telegram Mini App SDK (must load from telegram.org, not bundled) --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 pb-24 pt-4">
        {{ $slot }}
    </div>

    {{-- First-run onboarding --}}
    @include('partials.onboarding')

    {{-- Bottom navigation --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-white/5 bg-ink-850/95 backdrop-blur">
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
