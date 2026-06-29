<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->index();
            $table->string('type', 32)->nullable();
            $table->text('message');
            $table->foreignId('round_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('sent'); // sent | failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
