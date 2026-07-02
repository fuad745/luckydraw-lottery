<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The referral free-ticket reward was removed (invites now only count toward
 * the leaderboard). Free tickets were never redeemable anywhere, so clearing
 * the leftover balances loses nothing — it just stops stale numbers from ever
 * resurfacing in a future UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('players')->where('free_tickets', '>', 0)->update(['free_tickets' => 0]);
    }

    public function down(): void
    {
        // Irreversible: the old per-player counts are intentionally discarded.
    }
};
