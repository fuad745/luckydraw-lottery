<?php

namespace Database\Seeders;

use App\Enums\RoundStatus;
use App\Models\Player;
use App\Models\Round;
use App\Services\PrizeCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a demo open round so the Mini App has something to show.
     */
    public function run(): void
    {
        // Local dev: fund the impersonated user so you can test buying in a browser.
        if (app()->environment('local') && env('DEV_TELEGRAM_ID')) {
            Player::firstOrCreate(
                ['telegram_id' => (int) env('DEV_TELEGRAM_ID')],
                ['name' => env('DEV_TELEGRAM_NAME', 'Dev Admin'), 'referral_code' => Str::upper(Str::random(8)), 'balance' => 5000],
            );
        }

        if (Round::query()->exists()) {
            return;
        }

        Round::create([
            'title' => 'LuckyDraw Launch Round',
            'total_tickets' => 30,
            'ticket_price' => 50,
            'currency' => config('lottery.currency', 'ETB'),
            'status' => RoundStatus::Open,
            'winners_count' => 3,
            'prize_structure' => PrizeCalculator::defaultStructure(3), // 70% / 15% / 1 ticket price
            'allow_half_tickets' => true,
            'auto_draw' => true,
            'started_at' => now(),
        ]);
    }
}
