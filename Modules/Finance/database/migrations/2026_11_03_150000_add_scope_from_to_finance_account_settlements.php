<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_account_settlements', function (Blueprint $table): void {
            // Historical rows predate this field. Never invent a stored starting date.
            $table->date('scope_from')->nullable()->after('scope_to');
        });
    }

    public function down(): void
    {
        // Settlement period is immutable customer-facing evidence once recorded.
        if (DB::table('finance_account_settlements')->whereNotNull('scope_from')->exists()) {
            throw new \RuntimeException('Cannot discard registered settlement period dates.');
        }

        Schema::table('finance_account_settlements', function (Blueprint $table): void {
            $table->dropColumn('scope_from');
        });
    }
};
