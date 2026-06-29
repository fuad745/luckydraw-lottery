<?php

/** @var Nutgram $bot */

use App\Enums\RoundStatus;
use App\Models\Round;
use App\Services\PlayerService;
use App\Telegram\MiniApp;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

/*
|--------------------------------------------------------------------------
| LuckyDraw Telegram Bot Handlers
|--------------------------------------------------------------------------
| Commands: /start /mytickets /results /admin /help
| Loaded automatically by Nutgram's service provider.
*/

/** Resolve (and persist) the player behind the current Telegram update. */
$player = static function (Nutgram $bot, ?string $referredBy = null) {
    $from = $bot->user();
    $name = trim(($from->first_name ?? '').' '.($from->last_name ?? '')) ?: ($from->username ?? 'Player');

    return app(PlayerService::class)->resolve(
        telegramId: $from->id,
        name: $name,
        username: $from->username,
        referredByCode: $referredBy,
    );
};

$isAdmin = static fn (Nutgram $bot): bool => in_array(
    (string) $bot->userId(),
    (array) config('lottery.admin_telegram_ids', []),
    true,
);

/*
| /start — welcome + open the Mini App. Honours ?start=ref_CODE deep links.
*/
$startHandler = static function (Nutgram $bot, ?string $payload = null) use ($player): void {
    $ref = ($payload && str_starts_with($payload, 'ref_')) ? substr($payload, 4) : null;
    $me = $player($bot, $ref);

    $bot->sendMessage(
        text: "🎰 <b>Welcome to LuckyDraw, {$me->name}!</b>\n\n".
            "Buy tickets, pick your lucky number, split tickets with friends, and win the prize pool when the draw happens.\n\n".
            "Tap the button below to open the game 👇\n\n".
            "<i>Your referral link:</i>\n".$me->referralLink((string) config('lottery.bot_username')),
        parse_mode: ParseMode::HTML,
        reply_markup: MiniApp::button('🎟 Open LuckyDraw'),
    );
};

$bot->onCommand('start {payload}', $startHandler)->description('Open the LuckyDraw Mini App');
$bot->onCommand('start', $startHandler)->description('Open the LuckyDraw Mini App');

/*
| /help — how to play.
*/
$bot->onCommand('help', function (Nutgram $bot): void {
    $bot->sendMessage(
        text: "❓ <b>How to play LuckyDraw</b>\n\n".
            "1️⃣ Top up your wallet (Telebirr/CBE) — deposits are verified automatically.\n".
            "2️⃣ Tap numbers on the live board to pick tickets — buy a full or a ½ share.\n".
            "3️⃣ Share a number with a friend: each buys a half (50/50).\n".
            "4️⃣ When the last ticket sells (or the deadline hits), the draw runs automatically.\n".
            "5️⃣ Winners are paid straight to their wallet — cash out anytime.\n\n".
            "🏆 Multiple winners share the pot by tier; ½-tickets split their share.\n".
            "👥 Invite friends with your referral link for free tickets!\n\n".
            'Commands: /start /balance /mytickets /results /help',
        parse_mode: ParseMode::HTML,
    );
})->description('How to play');

/*
| /mytickets — the player's tickets in the latest round they joined.
*/
$bot->onCommand('mytickets', function (Nutgram $bot) use ($player): void {
    $me = $player($bot);

    $tickets = $me->tickets()
        ->with('round')
        ->latest('id')
        ->limit(20)
        ->get();

    if ($tickets->isEmpty()) {
        $bot->sendMessage(
            text: "🎫 You don't own any tickets yet.\nOpen the Mini App to buy your first one!",
            parse_mode: ParseMode::HTML,
            reply_markup: MiniApp::button('🎟 Buy a ticket'),
        );

        return;
    }

    $lines = $tickets->map(function ($t): string {
        $split = $t->is_split ? ' 🤝' : '';
        $win = $t->is_winner ? ' 🏆' : '';

        return "• <b>#{$t->ticket_number}</b> — {$t->round->title}{$split}{$win}";
    })->implode("\n");

    $bot->sendMessage(
        text: "🎫 <b>Your tickets</b>\n\n{$lines}",
        parse_mode: ParseMode::HTML,
        reply_markup: MiniApp::button('🎟 Open LuckyDraw', 'my-tickets'),
    );
})->description('Show your tickets');

/*
| /balance — wallet balance + open the wallet.
*/
$bot->onCommand('balance', function (Nutgram $bot) use ($player): void {
    $me = $player($bot);

    $bot->sendMessage(
        text: "👛 <b>Your wallet</b>\nBalance: <b>".number_format((float) $me->balance, 2).' '.config('lottery.currency')."</b>\n\nTop up, play, or cash out in the app.",
        parse_mode: ParseMode::HTML,
        reply_markup: MiniApp::button('👛 Open Wallet', 'wallet'),
    );
})->description('Your wallet balance');

/*
| /results — the latest finished round result.
*/
$bot->onCommand('results', function (Nutgram $bot): void {
    $round = Round::where('status', RoundStatus::Closed->value)
        ->whereNotNull('winner_ticket_id')
        ->with('winners')
        ->latest('drawn_at')
        ->first();

    if ($round === null || $round->winners->isEmpty()) {
        $bot->sendMessage(text: '📭 No draw has been completed yet. Stay tuned!');

        return;
    }

    $lines = $round->winners->map(function ($w): string {
        $medal = ['🥇', '🥈', '🥉'][($w->win_rank ?? 1) - 1] ?? '🏅';
        $who = $w->is_split ? $w->ownershipLabel() : $w->owner_name;

        return "{$medal} #{$w->win_rank} — Ticket <b>#{$w->ticket_number}</b> (".e($who).") → <b>{$w->prize_amount} {$round->currency}</b>";
    })->implode("\n");

    $bot->sendMessage(
        text: "🏆 <b>Latest results — {$round->title}</b>\n\n{$lines}\n\nPrize pool: <b>{$round->prizePool()} {$round->currency}</b>",
        parse_mode: ParseMode::HTML,
        reply_markup: MiniApp::button('📜 View history', 'history'),
    );
})->description('Latest draw result');

/*
| /admin — restricted control panel (Mini App) + quick stats.
*/
$bot->onCommand('admin', function (Nutgram $bot) use ($isAdmin): void {
    if (! $isAdmin($bot)) {
        $bot->sendMessage(text: '⛔ This command is restricted to administrators.');

        return;
    }

    $round = Round::current();
    $stats = $round
        ? "📊 <b>{$round->title}</b>\nStatus: {$round->status->label()}\nSold: {$round->ticketsSold()}/{$round->total_tickets}\nPrize pool: {$round->prizePool()} {$round->currency}"
        : 'No active round.';

    // The operator panel is browser-only (password protected) — not a Mini App.
    $adminUrl = rtrim((string) config('lottery.mini_app_url'), '/').'/admin';

    $bot->sendMessage(
        text: "🛠 <b>Admin</b>\n\n{$stats}\n\nManage everything in the browser panel:\n{$adminUrl}",
        parse_mode: ParseMode::HTML,
    );
})->description('Admin control panel');

/*
| Fallback for unknown messages.
*/
$bot->fallback(function (Nutgram $bot): void {
    $bot->sendMessage(
        text: "🤖 I didn't get that. Use /start to open the game or /help to learn how to play.",
        reply_markup: MiniApp::button('🎟 Open LuckyDraw'),
    );
});
