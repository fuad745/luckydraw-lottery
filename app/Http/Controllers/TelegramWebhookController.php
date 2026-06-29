<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Nutgram\Laravel\RunningMode\LaravelWebhook;
use SergiX44\Nutgram\Nutgram;
use Symfony\Component\HttpFoundation\Response;

final class TelegramWebhookController extends Controller
{
    public function __invoke(Nutgram $bot, string $token): Response
    {
        // The path token must match the bot token — blocks spoofed updates.
        abort_unless(hash_equals((string) config('lottery.bot_token'), $token), 404);

        $bot->setRunningMode(LaravelWebhook::class);
        $bot->run();

        return response('ok');
    }
}
