<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The old unique(provider, reference) index spanned ALL transaction types, but
 * withdrawals reuse `reference` to store the payout account — so a second
 * payout to the same account violated the index and crashed. Scope uniqueness
 * to deposits via a dedicated column that only deposit rows populate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('deposit_reference')->nullable()->after('reference');
        });

        // Preserve existing deposit dedup: backfill from the deposits' references.
        DB::table('transactions')->where('type', 'deposit')->whereNotNull('reference')
            ->update(['deposit_reference' => DB::raw('reference')]);

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'reference']);
            // Non-deposit rows leave deposit_reference NULL, and composite indexes
            // treat NULL as distinct — so repeat withdrawals never collide.
            $table->unique(['provider', 'deposit_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'deposit_reference']);
            $table->unique(['provider', 'reference']);
            $table->dropColumn('deposit_reference');
        });
    }
};
