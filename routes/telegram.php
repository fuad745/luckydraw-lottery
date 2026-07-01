<?php

/** @var Nutgram $bot */

use App\Enums\RoundStatus;
use App\Models\Player;
use App\Models\Round;
use App\Services\DepositService;
use App\Services\PaymentMessageParser;
use App\Services\PlayerService;
use App\Support\Phone;
use App\Telegram\MiniApp;
use Illuminate\Validation\ValidationException;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatAction;
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

$bot->onCommand('start {payload}', $startHandler);
$bot->onCommand('start', $startHandler);

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
            "💵 <i>To deposit fast: just paste your Telebirr/CBE/M-Pesa payment SMS into this chat.</i>\n\n".
            'Commands: /start /balance /mytickets /results /leaderboard /help',
        parse_mode: ParseMode::HTML,
    );
});

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
});

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
});

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
});

/*
| /leaderboard — top inviters and top winners, for a little social pull.
*/
$bot->onCommand('leaderboard', function (Nutgram $bot): void {
    $currency = config('lottery.currency');
    $medal = static fn (int $i): string => ['🥇', '🥈', '🥉'][$i] ?? '🏅';

    $inviters = Player::where('referral_count', '>', 0)
        ->orderByDesc('referral_count')->limit(5)->get(['name', 'referral_count']);

    $winners = Player::where('total_winnings', '>', 0)
        ->orderByDesc('total_winnings')->limit(5)->get(['name', 'total_winnings']);

    if ($inviters->isEmpty() && $winners->isEmpty()) {
        $bot->sendMessage(
            text: "📊 The leaderboard is empty — be the first!\nInvite friends and win draws to climb it.",
            reply_markup: MiniApp::button('🎟 Open LuckyDraw'),
        );

        return;
    }

    $sections = [];

    if ($inviters->isNotEmpty()) {
        $lines = $inviters->values()->map(
            fn ($p, $i) => $medal($i).' '.e($p->name)." — <b>{$p->referral_count}</b> invited"
        )->implode("\n");
        $sections[] = "👥 <b>Top inviters</b>\n{$lines}";
    }

    if ($winners->isNotEmpty()) {
        $lines = $winners->values()->map(
            fn ($p, $i) => $medal($i).' '.e($p->name).' — <b>'.number_format((float) $p->total_winnings, 2)." {$currency}</b>"
        )->implode("\n");
        $sections[] = "🏆 <b>Top winners</b>\n{$lines}";
    }

    $bot->sendMessage(
        text: "📊 <b>LuckyDraw Leaderboard</b>\n\n".implode("\n\n", $sections),
        parse_mode: ParseMode::HTML,
        reply_markup: MiniApp::button('🎟 Play & climb the board'),
    );
});

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
});

/*
| Capture a shared phone number. The Mini App's "Share contact" prompt (and a
| reply-keyboard contact button) deliver the contact here — we save it to the
| player so they're "registered" and can be paid out. This is the reliable,
| server-side half of the registration flow.
*/
$bot->onContact(function (Nutgram $bot) use ($player): void {
    $contact = $bot->message()?->contact;
    if ($contact === null) {
        return;
    }

    // Only accept the user's OWN contact (Telegram sets user_id to the sharer).
    if ($contact->user_id !== null && (int) $contact->user_id !== (int) $bot->userId()) {
        $bot->sendMessage(text: '⚠️ Please share *your own* contact to register.', parse_mode: ParseMode::MARKDOWN);

        return;
    }

    $phone = Phone::normalize($contact->phone_number);
    if ($phone === null) {
        return;
    }

    $me = $player($bot);
    $me->update(['phone' => $phone]);

    $bot->sendMessage(
        text: "✅ <b>Thanks, {$me->name}!</b>\nYour contact is saved — you're all set. Tap below to play 👇",
        parse_mode: ParseMode::HTML,
        reply_markup: MiniApp::button('🎟 Open LuckyDraw'),
    );
});

/*
| Deposit by DM — a player can just paste their payment SMS or receipt link
| (Telebirr / CBE / CBE Birr / M-Pesa) into the chat and we verify + credit it,
| no Mini App needed. Returns true when the text was a recognisable payment
| message we handled (success or a friendly error), false otherwise.
*/
$tryDeposit = static function (Nutgram $bot, string $text) use ($player): bool {
    // Only auto-credit when the message carries a confident provider signal
    // (a receipt link, an FT reference, or an M-Pesa marker). Ambiguous bare
    // codes are left to the Mini App, where the method is chosen explicitly.
    $parsed = app(PaymentMessageParser::class)->parse($text);
    if ($parsed['provider'] === null || $parsed['reference'] === null) {
        return false;
    }

    $bot->sendChatAction(ChatAction::TYPING);
    $me = $player($bot);

    try {
        // DepositService re-parses the full text itself; we pass the detected
        // provider and skip its queued notice in favour of the reply below.
        $tx = app(DepositService::class)->deposit($me, $parsed['provider'], $text, notify: false);
    } catch (ValidationException $e) {
        $bot->sendMessage(
            text: '⚠️ '.e((string) collect($e->errors())->flatten()->first()),
            parse_mode: ParseMode::HTML,
            reply_markup: MiniApp::button('👛 Open Wallet', 'wallet'),
        );

        return true;
    } catch (Throwable $e) {
        report($e);
        $bot->sendMessage(text: '⚠️ We could not verify that just now. Please try again shortly.');

        return true;
    }

    $bot->sendMessage(
        text: "✅ <b>Deposit confirmed!</b>\n+".number_format((float) $tx->amount, 2).' '.config('lottery.currency').
            "\n💼 New balance: <b>".number_format((float) $me->fresh()->balance, 2).' '.config('lottery.currency').'</b>',
        parse_mode: ParseMode::HTML,
        reply_markup: MiniApp::button('🎟 Open LuckyDraw'),
    );

    return true;
};

/*
| Fallback for unknown messages. First tries to read the text as a pasted
| payment message (deposit by DM); otherwise shows the generic help.
*/
$bot->fallback(function (Nutgram $bot) use ($tryDeposit): void {
    // Deposits only make sense in a 1:1 chat, never in groups/channels.
    $text = $bot->message()?->text;
    $isPrivate = $bot->chat()?->isPrivate() ?? false;

    if ($isPrivate && $text !== null && $tryDeposit($bot, $text)) {
        return;
    }

    $bot->sendMessage(
        text: "🤖 I didn't get that. Use /start to open the game or /help to learn how to play.\n\n".
            '💡 <i>Tip: to deposit, just paste your Telebirr/CBE/M-Pesa payment SMS or receipt link here.</i>',
        parse_mode: ParseMode::HTML,
        reply_markup: MiniApp::button('🎟 Open LuckyDraw'),
    );
});
