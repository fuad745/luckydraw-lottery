<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoundStatus;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
final class RoundFactory extends Factory
{
    protected $model = Round::class;

    public function definition(): array
    {
        return [
            'title' => 'LuckyDraw #'.fake()->numberBetween(1, 999),
            'total_tickets' => fake()->randomElement([10, 25, 50, 100]),
            'ticket_price' => fake()->randomElement([10, 25, 50, 100]),
            'currency' => 'ETB',
            'status' => RoundStatus::Open,
            'auto_draw' => true,
            'started_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => RoundStatus::Closed,
            'drawn_at' => now(),
        ]);
    }
}
