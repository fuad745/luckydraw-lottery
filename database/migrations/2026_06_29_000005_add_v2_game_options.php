<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table): void {
            $table->unsignedInteger('winners_count')->default(1)->after('ticket_price');
            // Ordered list of prize tiers, e.g. [{"type":"percent","value":70},{"type":"ticket_price"}].
            $table->json('prize_structure')->nullable()->after('winners_count');
            $table->boolean('allow_half_tickets')->default(true)->after('prize_structure');
            $table->boolean('auto_restart')->default(false)->after('auto_draw');
            $table->unsignedInteger('restart_delay_minutes')->default(5)->after('auto_restart');
            // Optional per-round channel override (else config/env TELEGRAM_CHANNEL_ID).
            $table->string('channel_id')->nullable()->after('restart_delay_minutes');
            // Amount kept by the house after winner payouts (recorded at draw time).
            $table->decimal('admin_cut', 12, 2)->default(0)->after('channel_id');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            // 1 = first place, 2 = second place, ... null = not a winner.
            $table->unsignedInteger('win_rank')->nullable()->after('is_winner');
            $table->decimal('prize_amount', 12, 2)->default(0)->after('win_rank');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table): void {
            $table->dropColumn([
                'winners_count', 'prize_structure', 'allow_half_tickets',
                'auto_restart', 'restart_delay_minutes', 'channel_id', 'admin_cut',
            ]);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn(['win_rank', 'prize_amount']);
        });
    }
};
