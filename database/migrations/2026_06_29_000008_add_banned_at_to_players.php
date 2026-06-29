<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->timestamp('banned_at')->nullable()->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('banned_at');
        });
    }
};
