<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Player>
 */
final class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'telegram_id' => fake()->unique()->numberBetween(100_000, 9_999_999_999),
            'name' => fake()->name(),
            'username' => fake()->optional()->userName(),
            'phone' => '+2519'.fake()->numerify('########'),
            'referral_code' => Str::upper(Str::random(8)),
            'referred_by' => null,
            'referral_count' => 0,
            'free_tickets' => 0,
            'total_tickets_bought' => 0,
            'total_wins' => 0,
        ];
    }
}
