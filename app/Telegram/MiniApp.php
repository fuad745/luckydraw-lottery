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
}
