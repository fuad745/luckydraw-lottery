<?php

declare(strict_types=1);

namespace App\Support;

final class Html
{
    /**
     * Escape a string for Telegram Bot API "HTML" parse mode, which only
     * recognises &amp; &lt; &gt;. Player names (and admin round titles) can
     * contain <, > or & — left raw they make Telegram reject the whole message
     * with HTTP 400, so every interpolated dynamic value must pass through here.
     *
     * Uses ENT_NOQUOTES so quotes stay literal (Telegram doesn't decode &quot;).
     */
    public static function tg(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
