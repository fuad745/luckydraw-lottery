<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            // Telegram user id is the natural primary key.
            $table->unsignedBigInteger('telegram_id')->primary();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('phone')->nullable();
            $table->string('referral_code', 16)->unique();
            $table->string('referred_by', 16)->nullable()->index();
            $table->unsignedInteger('referral_count')->default(0);
            $table->unsignedInteger('free_tickets')->default(0);
            $table->unsignedInteger('total_tickets_bought')->default(0);
            $table->unsignedInteger('total_wins')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
