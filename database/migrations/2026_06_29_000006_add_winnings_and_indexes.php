<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->decimal('total_winnings', 14, 2)->default(0)->after('total_wins');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            // Speeds up winner look-ups for results/history/my-tickets.
            $table->index(['round_id', 'is_winner']);
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('total_winnings');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['round_id', 'is_winner']);
        });
    }
};
