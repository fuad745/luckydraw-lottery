<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('total_tickets');
            $table->decimal('ticket_price', 12, 2);
            $table->string('currency', 8)->default('ETB');
            $table->string('status', 16)->default('open')->index();
            $table->boolean('auto_draw')->default(true);
            $table->timestamp('draw_deadline')->nullable();
            // No DB-level FK here so the schema stays SQLite/Postgres portable
            // (winner is created after the round). Relationship handled in Eloquent.
            $table->unsignedBigInteger('winner_ticket_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('drawn_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
