<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Rounds;
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
        Livewire::test(Rounds::class)
            ->set('totalTickets', '')      // cleared field → null
            ->set('ticketPrice', '')
            ->set('winnersCount', '')
            ->assertOk();                  // renders without a 500
    }

    public function test_submitting_with_an_empty_ticket_count_is_a_validation_error(): void
    {
        Livewire::test(Rounds::class)
            ->set('title', 'Friday Draw')
            ->set('totalTickets', '')
            ->call('createRound')
            ->assertHasErrors('totalTickets')
            ->assertOk();

        $this->assertDatabaseCount('rounds', 0);
    }

    public function test_a_valid_round_is_created(): void
    {
        Livewire::test(Rounds::class)
            ->set('title', 'Friday Draw')
            ->set('totalTickets', 10)
            ->set('ticketPrice', 25)
            ->call('createRound')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('rounds', ['title' => 'Friday Draw', 'total_tickets' => 10]);
    }
}
