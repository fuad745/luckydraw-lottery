<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            // One-shot guard: the referrer is rewarded exactly once, when this
            // flips from null → timestamp on the referred player's first buy.
            $table->timestamp('referral_rewarded_at')->nullable()->after('referred_by');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('referral_rewarded_at');
        });
    }
};
