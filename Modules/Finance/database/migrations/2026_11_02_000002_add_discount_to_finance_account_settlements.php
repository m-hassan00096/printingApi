<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_account_settlements', function (Blueprint $table): void {
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->text('discount_reason')->nullable();
            $table->foreignId('discount_ledger_entry_id')->nullable()
                ->constrained('finance_ledger_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Historical settlement evidence and posted ledger movements must not be
        // deleted or silently reinterpreted by a schema rollback.
        if (\Illuminate\Support\Facades\DB::table('finance_account_settlements')
            ->where('discount_amount', '>', 0)->exists()) {
            throw new \RuntimeException('Cannot remove settlement discount evidence from a database containing discounts.');
        }

        Schema::table('finance_account_settlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_ledger_entry_id');
            $table->dropColumn(['discount_amount', 'discount_reason']);
        });
    }
};
