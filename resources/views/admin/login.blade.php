<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0e14">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin login · LuckyDraw</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh items-center justify-center bg-ink-900 px-4 text-slate-100">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <h1 class="text-3xl font-black gold-text">LuckyDraw</h1>
            <p class="mt-1 text-sm text-slate-400">Operator panel</p>
        </div>

        <form method="POST" action="{{ route('admin.login.attempt') }}" class="card space-y-4 p-6">
            @csrf

            @error('username')
                <div role="alert" class="rounded-xl bg-rose-500/15 px-3 py-2 text-sm text-rose-300">{{ $message }}</div>
            @enderror

            <div>
                <label for="username" class="label">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required autofocus
                       value="{{ old('username') }}" class="input" placeholder="username">
            </div>

            <div x-data="{ show: false }">
                <label for="password" class="label">Password</label>
                <div class="relative">
                    <input id="password" name="password" :type="show ? 'text' : 'password'" autocomplete="current-password" required
                           class="input pr-12" placeholder="••••••••">
                    <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400 hover:text-slate-200"
                            aria-label="Toggle password visibility" x-text="show ? 'Hide' : 'Show'"></button>
                </div>
            </div>

            <button type="submit" class="btn-gold w-full">Sign in</button>
        </form>

        <p class="mt-4 text-center text-xs text-slate-600">Authorized access only.</p>
    </div>
</body>
</html>
