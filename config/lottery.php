<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    | Telegram user id allowed to access the /admin command and the admin
    | Mini App panel. Comma-separated for multiple admins.
    */
    'admin_telegram_ids' => array_filter(
        array_map('trim', explode(',', (string) env('ADMIN_TELEGRAM_ID', '')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Admin panel (browser-only, password protected)
    |--------------------------------------------------------------------------
    | The operator dashboard at /admin is NOT part of the Telegram Mini App.
    | Set ADMIN_PANEL_PASSWORD_HASH (bcrypt) in production; the plaintext
    | ADMIN_PANEL_PASSWORD is a convenience fallback for first run.
    */
    'admin_panel' => [
        'username' => env('ADMIN_PANEL_USERNAME', 'kichner'),
        'password' => env('ADMIN_PANEL_PASSWORD', 'blackflag'),
        'password_hash' => env('ADMIN_PANEL_PASSWORD_HASH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram
    |--------------------------------------------------------------------------
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),
    'mini_app_url' => env('MINI_APP_URL', env('APP_URL')),

    // Public channel/group where winner results are posted (e.g. @luckydraw or -100123...).
    // Optional — leave blank to skip channel posts. The bot must be an admin of the channel.
    'channel_id' => env('TELEGRAM_CHANNEL_ID'),

    /*
    |--------------------------------------------------------------------------
    | Gameplay
    |--------------------------------------------------------------------------
    */
    'currency' => env('LOTTERY_CURRENCY', 'ETB'),

    // Seconds of suspense between locking sales and revealing the winner.
    'draw_suspense_seconds' => (int) env('LOTTERY_DRAW_SUSPENSE', 10),

    // Free tickets awarded to a referrer when a referred player first buys.
    'referral_reward_tickets' => (int) env('LOTTERY_REFERRAL_REWARD', 1),

    // Max tickets a single buyer may purchase in one transaction.
    'max_tickets_per_purchase' => (int) env('LOTTERY_MAX_PER_PURCHASE', 20),

    // Also DM every known player when a new round starts (re-engagement).
    // Can be noisy — off by default; new rounds are always posted to the channel.
    'announce_to_players' => (bool) env('LOTTERY_ANNOUNCE_PLAYERS', false),

    /*
    |--------------------------------------------------------------------------
    | Wallet & payments (verify.leul.et)
    |--------------------------------------------------------------------------
    | Deposits are auto-verified against a real payment reference
    | (Telebirr / CBE / CBE Birr / M-Pesa). Withdrawals are paid out by an
    | admin and marked completed.
    */
    'payments' => [
        'verify_url' => env('VERIFY_API_URL', 'https://verifyapi.leulzenebe.pro'),
        'verify_key' => env('VERIFY_API_KEY'),

        // Providers offered in the deposit screen.
        'providers' => ['telebirr', 'cbe', 'cbebirr', 'mpesa'],

        // Your receiving account names/numbers. A deposit only counts if its
        // verified receiver matches one of these (anti-fraud). Comma-separated.
        // Strongly recommended in production; if empty, the receiver is not checked.
        'deposit_accounts' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('DEPOSIT_ACCOUNTS', '')))
        )),

        'min_deposit' => (float) env('LOTTERY_MIN_DEPOSIT', 10),
        'min_withdraw' => (float) env('LOTTERY_MIN_WITHDRAW', 50),

        // Where players send deposits — shown as instructions in the app.
        'deposit_instructions' => env('LOTTERY_DEPOSIT_INSTRUCTIONS',
            'Send the amount to our Telebirr/CBE account, then paste the transaction reference below.'),
    ],

];
