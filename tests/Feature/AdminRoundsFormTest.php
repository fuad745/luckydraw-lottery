<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoundStatus;
use App\Livewire\Admin\CreateRound;
use App\Livewire\Admin\Rounds;
use App\Models\Player;
use App\Models\Round;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminRoundsFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        session(['admin_authenticated' => true]);
    }

    public function test_clearing_the_tickets_field_does_not_500(): void
    {
        // Reproduces the reported bug: emptying a live-bound number input sent
        // an empty string that used to blow up hydrating a typed int.
        Livewire::test(CreateRound::class)
            ->set('totalTickets', '')      // cleared field → null
            ->set('ticketPrice', '')
            ->set('winnersCount', '')
            ->assertOk();                  // renders without a 500
    }

    public function test_submitting_with_an_empty_ticket_count_is_a_validation_error(): void
    {
        Livewire::test(CreateRound::class)
            ->set('title', 'Friday Draw')
            ->set('totalTickets', '')
            ->call('createRound')
            ->assertHasErrors('totalTickets')
            ->assertOk();

        $this->assertDatabaseCount('rounds', 0);
    }

    public function test_a_valid_round_is_created_and_redirects_to_rounds(): void
    {
        Livewire::test(CreateRound::class)
            ->set('title', 'Friday Draw')
            ->set('totalTickets', 10)
            ->set('ticketPrice', 25)
            ->call('createRound')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.rounds'));

        $this->assertDatabaseHas('rounds', ['title' => 'Friday Draw', 'total_tickets' => 10]);
    }

    public function test_a_round_with_no_players_can_be_deleted(): void
    {
        $round = Round::create([
            'title' => 'Empty Round',
            'total_tickets' => 10,
            'ticket_price' => 25,
            'currency' => 'ETB',
            'status' => RoundStatus::Open,
            'winners_count' => 1,
            'started_at' => now(),
        ]);

        Livewire::test(Rounds::class)
            ->call('deleteRound', $round->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('rounds', ['id' => $round->id]);
    }

    public function test_a_round_with_tickets_cannot_be_deleted(): void
    {
        $round = Round::create([
            'title' => 'Busy Round',
            'total_tickets' => 10,
            'ticket_price' => 25,
            'currency' => 'ETB',
            'status' => RoundStatus::Open,
            'winners_count' => 1,
            'started_at' => now(),
        ]);

        $buyer = Player::factory()->create();
        Ticket::create([
            'round_id' => $round->id,
            'ticket_number' => 1,
            'owner_telegram_id' => $buyer->telegram_id,
            'owner_name' => $buyer->name,
            'owner_phone' => '+251900000001',
            'purchased_at' => now(),
        ]);

        Livewire::test(Rounds::class)
            ->call('deleteRound', $round->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertDatabaseHas('rounds', ['id' => $round->id]);
    }
}
