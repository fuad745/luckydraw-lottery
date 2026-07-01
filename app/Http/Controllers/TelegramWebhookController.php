<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Nutgram\Laravel\RunningMode\LaravelWebhook;
use SergiX44\Nutgram\Nutgram;
use Symfony\Component\HttpFoundation\Response;

final class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, Nutgram $bot, string $token): Response
    {
        // The path token must match the bot token — blocks spoofed updates.
        abort_unless(hash_equals((string) config('lottery.bot_token'), $token), 404);

        // Defense-in-depth: if a webhook secret is configured, the header
        // Telegram echoes back must match it (URLs can leak from logs/proxies).
        $secret = (string) config('lottery.webhook_secret');
        if ($secret !== '') {
            abort_unless(
                hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '')),
                404,
            );
        }

        $bot->setRunningMode(LaravelWebhook::class);
        $bot->run();

        return response('ok');
    }
}
