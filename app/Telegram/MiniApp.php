<?php

declare(strict_types=1);

namespace App\Telegram;

use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

final class MiniApp
{
    public const PARSE_MODE = ParseMode::HTML;

    public static function url(?string $path = null): string
    {
        $base = rtrim((string) config('lottery.mini_app_url'), '/');

        return $path ? $base.'/'.ltrim($path, '/') : $base;
    }

    /** A one-button inline keyboard that launches the Mini App. */
    public static function button(string $label = '🎟 Open LuckyDraw', ?string $path = null): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make(
                text: $label,
                web_app: WebAppInfo::make(url: self::url($path)),
            ),
        );
    }

    /**
     * Raw reply_markup array with a web_app button. Works in PRIVATE chats
     * (DMs) — one tap opens the Mini App. Used for queued sends/broadcasts.
     *
     * @return array{inline_keyboard:array<int,array<int,array<string,mixed>>>}
     */
    public static function webAppButton(string $label = '🎟 Open LuckyDraw', ?string $path = null): array
    {
        return ['inline_keyboard' => [[['text' => $label, 'web_app' => ['url' => self::url($path)]]]]];
    }

    /**
     * Raw reply_markup array with a deep-link URL button to the bot chat.
     * web_app buttons are disallowed in channels/groups, so channel posts use
     * this: it opens the bot (honouring the ?start= payload), where /start then
     * offers the Mini App button. Null when no bot username is configured.
     *
     * @return array{inline_keyboard:array<int,array<int,array<string,string>>>}|null
     */
    public static function chatLinkButton(string $label = '🎟 Play LuckyDraw', string $startParam = 'play'): ?array
    {
        $bot = trim((string) config('lottery.bot_username'), " \t@");
        if ($bot === '') {
            return null;
        }

        $url = "https://t.me/{$bot}".($startParam !== '' ? '?start='.rawurlencode($startParam) : '');

        return ['inline_keyboard' => [[['text' => $label, 'url' => $url]]]];
    }
}
