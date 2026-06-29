<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ticket_number');

            $table->string('owner_name');
            $table->string('owner_phone');
            $table->unsignedBigInteger('owner_telegram_id')->index();

            // Split-ticket co-owner (50/50)
            $table->boolean('is_split')->default(false);
            $table->string('co_owner_name')->nullable();
            $table->string('co_owner_phone')->nullable();
            $table->unsignedBigInteger('co_owner_telegram_id')->nullable()->index();

            $table->boolean('is_winner')->default(false);
            $table->string('referred_by', 16)->nullable();
            $table->timestamp('purchased_at');
            $table->timestamps();

            // A ticket number is unique within its round.
            $table->unique(['round_id', 'ticket_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
