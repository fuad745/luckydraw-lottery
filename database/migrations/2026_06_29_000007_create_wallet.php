<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->decimal('balance', 14, 2)->default(0)->after('total_winnings');
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telegram_id')->index();
            $table->string('type', 16);                 // deposit | withdrawal | purchase | winning | refund | adjustment
            $table->string('status', 16)->default('completed');
            $table->decimal('amount', 14, 2);           // always positive; type decides direction
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->string('provider', 16)->nullable(); // telebirr | cbe | cbebirr | mpesa | wallet
            $table->string('reference')->nullable();    // payment reference / payout account
            $table->foreignId('round_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();           // raw verification payload, payout details
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // A payment reference can only ever be credited once.
            $table->unique(['provider', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');

        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('balance');
        });
    }
};
