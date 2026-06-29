<?php

namespace Tests;

use App\Models\Player;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Create a player with a funded wallet (purchases now spend balance). */
    protected function fundedPlayer(int $telegramId, float $balance = 1_000_000): Player
    {
        return Player::factory()->create([
            'telegram_id' => $telegramId,
            'balance' => $balance,
        ]);
    }
}
