<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Round;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
final class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'ticket_number' => fake()->unique()->numberBetween(1, 100),
            'owner_name' => fake()->name(),
            'owner_phone' => '+2519'.fake()->numerify('########'),
            'owner_telegram_id' => fake()->numberBetween(100_000, 9_999_999_999),
            'is_split' => false,
            'is_winner' => false,
            'purchased_at' => now(),
        ];
    }

    public function split(): static
    {
        return $this->state(fn () => [
            'is_split' => true,
            'co_owner_name' => fake()->name(),
            'co_owner_phone' => '+2519'.fake()->numerify('########'),
            'co_owner_telegram_id' => fake()->numberBetween(100_000, 9_999_999_999),
        ]);
    }
}
