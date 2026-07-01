<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Players;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminPlayerAdjustTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        session(['admin_authenticated' => true]);
    }

    public function test_clearing_the_adjust_amount_is_a_validation_error_not_a_500(): void
    {
        $player = Player::factory()->create(['balance' => 100]);

        Livewire::test(Players::class)
            ->call('openAdjust', $player->telegram_id)
            ->set('adjustAmount', '')     // cleared field → null, not a 500
            ->call('saveAdjust')
            ->assertHasErrors('adjustAmount')
            ->assertOk();

        $this->assertSame(100.0, (float) $player->fresh()->balance);
    }

    public function test_a_valid_adjustment_credits_the_player(): void
    {
        $player = Player::factory()->create(['balance' => 100]);

        Livewire::test(Players::class)
            ->call('openAdjust', $player->telegram_id)
            ->set('adjustAmount', 50)
            ->call('saveAdjust')
            ->assertHasNoErrors();

        $this->assertSame(150.0, (float) $player->fresh()->balance);
    }
}
