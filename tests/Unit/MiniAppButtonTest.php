<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Telegram\MiniApp;
use Tests\TestCase;

final class MiniAppButtonTest extends TestCase
{
    public function test_web_app_button_carries_the_mini_app_url(): void
    {
        config(['lottery.mini_app_url' => 'https://app.example.com']);

        $markup = MiniApp::webAppButton('Play', 'wallet');
        $btn = $markup['inline_keyboard'][0][0];

        $this->assertSame('Play', $btn['text']);
        $this->assertSame('https://app.example.com/wallet', $btn['web_app']['url']);
    }

    public function test_chat_link_button_builds_a_start_deep_link(): void
    {
        config(['lottery.bot_username' => 'LuckyDrawBot']);

        $btn = MiniApp::chatLinkButton('Play', 'play')['inline_keyboard'][0][0];

        $this->assertSame('https://t.me/LuckyDrawBot?start=play', $btn['url']);
    }

    public function test_chat_link_button_is_null_without_a_bot_username(): void
    {
        config(['lottery.bot_username' => '']);

        $this->assertNull(MiniApp::chatLinkButton('Play'));
    }
}
