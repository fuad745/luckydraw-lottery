<?php

use App\Http\Controllers\Admin\TransactionExportController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\TelegramWebhookController;
use App\Livewire\Admin\Broadcast;
use App\Livewire\Admin\CreateRound;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Players;
use App\Livewire\Admin\Rounds;
use App\Livewire\Admin\Settings as AdminSettings;
use App\Livewire\Admin\Transactions;
use App\Livewire\Admin\Withdrawals;
use App\Livewire\History;
use App\Livewire\Leaderboard;
use App\Livewire\LotteryHome;
use App\Livewire\MyTickets;
use App\Livewire\Register;
use App\Livewire\Settings;
use App\Livewire\Wallet;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Telegram Mini App (Livewire)
|--------------------------------------------------------------------------
*/
// Registration (share contact) — must NOT carry the `registered` gate itself.
Route::get('/register', Register::class)->name('register');

// The game — only reachable once the player has shared their contact.
Route::middleware('registered')->group(function (): void {
    Route::get('/', LotteryHome::class)->name('home');
    Route::get('/wallet', Wallet::class)->name('wallet');
    Route::get('/my-tickets', MyTickets::class)->name('my-tickets');
    Route::get('/history', History::class)->name('history');
    Route::get('/leaderboard', Leaderboard::class)->name('leaderboard');
    Route::get('/settings', Settings::class)->name('settings');
});

/*
|--------------------------------------------------------------------------
| Operator admin panel (browser-only, password protected — NOT Telegram)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function (): void {
    Route::get('login', [AdminAuthController::class, 'show'])->name('admin.login');
    Route::post('login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:10,1')->name('admin.login.attempt');
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::middleware('admin')->group(function (): void {
        Route::get('/', Dashboard::class)->name('admin');
        Route::get('rounds', Rounds::class)->name('admin.rounds');
        Route::get('rounds/create', CreateRound::class)->name('admin.rounds.create');
        Route::get('players', Players::class)->name('admin.players');
        Route::get('withdrawals', Withdrawals::class)->name('admin.withdrawals');
        Route::get('transactions', Transactions::class)->name('admin.transactions');
        Route::get('transactions/export', TransactionExportController::class)->name('admin.transactions.export');
        Route::get('broadcast', Broadcast::class)->name('admin.broadcast');
        Route::get('settings', AdminSettings::class)->name('admin.settings');
    });
});

/*
|--------------------------------------------------------------------------
| Telegram Bot webhook (server-to-server)
|--------------------------------------------------------------------------
*/
Route::post('/telegram/webhook/{token}', TelegramWebhookController::class)
    ->name('telegram.webhook');
