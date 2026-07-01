<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Telegram\MiniApp;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Command\BotCommand;
use SergiX44\Nutgram\Telegram\Types\Command\MenuButtonWebApp;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

/**
 * One-shot configuration of the bot's Telegram-side chrome: the command menu
 * (the "/" dropdown), the persistent Mini App menu button, and — with
 * --webhook — the webhook itself plus its secret token.
 *
 * Run after deploy or whenever the command list changes:
 *   php artisan lottery:telegram-setup
 *   php artisan lottery:telegram-setup --webhook
 */
final class TelegramSetup extends Command
{
    protected $signature = 'lottery:telegram-setup {--webhook : Also (re)set the webhook and its secret token}';

    protected $description = 'Register the bot command menu, Mini App menu button, and (optionally) the webhook';

    /** The public command list shown in Telegram's "/" menu. */
    private const COMMANDS = [
        ['start', 'Open LuckyDraw and get your referral link'],
        ['balance', 'Check your wallet balance'],
        ['deposit', 'Add funds — paste your payment SMS'],
        ['mytickets', 'See your tickets'],
        ['results', 'Latest draw results'],
        ['leaderboard', 'Top inviters and winners'],
        ['help', 'How to play & how to deposit'],
    ];

    public function handle(Nutgram $bot): int
    {
        if (empty(config('lottery.bot_token'))) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        // 1. Command menu.
        $bot->setMyCommands(array_map(
            fn (array $c) => BotCommand::make($c[0], $c[1]),
            self::COMMANDS,
        ));
        $this->info('✔ Command menu registered ('.count(self::COMMANDS).' commands).');

        // 2. Persistent "Open App" menu button next to the message box.
        $bot->setChatMenuButton(menu_button: MenuButtonWebApp::make(
            text: '🎟 Play',
            web_app: WebAppInfo::make(url: MiniApp::url()),
        ));
        $this->info('✔ Mini App menu button set.');

        // 3. Optionally (re)set the webhook with its secret token.
        if ($this->option('webhook')) {
            $url = route('telegram.webhook', ['token' => config('lottery.bot_token')]);
            $secret = (string) config('lottery.webhook_secret');

            $bot->setWebhook($url, secret_token: $secret !== '' ? $secret : null);
            $this->info('✔ Webhook set to '.$url.($secret !== '' ? ' (with secret token).' : ' (no secret configured).'));
        }

        return self::SUCCESS;
    }
}
