<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoundStatus;
use App\Livewire\LotteryHome;
use App\Models\Round;
use App\Models\Ticket;
use App\Telegram\TelegramAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

final class LotteryHomeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsTelegram(int $id = 4242, ?string $phone = '+251911000000'): void
    {
        $auth = app(TelegramAuth::class);
        $auth->setUser(['id' => $id, 'first_name' => 'Test', 'username' => 'tester', 'phone' => $phone]);
    }

    public function test_it_renders_the_board_for_an_open_round(): void
    {
        $this->actingAsTelegram();
        Round::factory()->create(['total_tickets' => 6, 'status' => RoundStatus::Open]);

        Livewire::test(LotteryHome::class)
            ->assertSee('Pick your numbers')
            ->assertSet('board', fn ($board) => count($board) === 6);
    }

    public function test_it_buys_picked_numbers_through_the_component(): void
    {
        Queue::fake();
        $this->actingAsTelegram();
        $this->fundedPlayer(4242); // wallet covers the purchase
        Round::factory()->create(['total_tickets' => 10, 'ticket_price' => 50, 'status' => RoundStatus::Open]);

        Livewire::test(LotteryHome::class)
            ->call('buy', [2, 5], [8]) // two full + one half
            ->assertDispatched('purchased');

        $this->assertSame(3, Ticket::count());
        $this->assertTrue(Ticket::where('ticket_number', 8)->first()->is_split);
    }

    public function test_it_blocks_buying_without_enough_balance(): void
    {
        $this->actingAsTelegram(); // player has no wallet balance
        Round::factory()->create(['total_tickets' => 10, 'ticket_price' => 50, 'status' => RoundStatus::Open]);

        Livewire::test(LotteryHome::class)
            ->call('buy', [1], [])
            ->assertDispatched('toast');

        $this->assertSame(0, Ticket::count());
    }
}
