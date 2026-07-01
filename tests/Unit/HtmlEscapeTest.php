<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaymentMessageParser;
use App\Support\Html;
use PHPUnit\Framework\TestCase;

final class HtmlEscapeTest extends TestCase
{
    public function test_tg_escapes_only_the_three_html_specials(): void
    {
        // A name/title with these characters would otherwise make Telegram
        // reject the whole message with HTTP 400.
        $this->assertSame('A &amp; B', Html::tg('A & B'));
        $this->assertSame('&lt;b&gt;x&lt;/b&gt;', Html::tg('<b>x</b>'));
        // Quotes are left literal (Telegram doesn't decode &quot;).
        $this->assertSame('O\'Brien "Ace"', Html::tg('O\'Brien "Ace"'));
        $this->assertSame('', Html::tg(null));
    }

    public function test_looks_like_payment_gates_casual_chatter(): void
    {
        $parser = new PaymentMessageParser;

        $this->assertTrue($parser->looksLikePayment('You received ETB 500.00 via telebirr'));
        $this->assertTrue($parser->looksLikePayment('https://apps.cbe.com.et:100/?id=FT25X'));
        $this->assertTrue($parser->looksLikePayment('Transferred 250.00 birr'));

        $this->assertFalse($parser->looksLikePayment('my code is FTABCD1234 lol'));
        $this->assertFalse($parser->looksLikePayment('hello there'));
    }
}
