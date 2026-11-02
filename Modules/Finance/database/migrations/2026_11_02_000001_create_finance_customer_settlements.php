<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_customer_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->text('discount_reason')->nullable();
            $table->decimal('collected_amount', 12, 2)->default(0);
            $table->foreignId('collection_id')->nullable()->constrained('finance_collections')->restrictOnDelete();
            $table->text('note')->nullable();
            // Keep the original issued statement intact after future payments or reversals.
            $table->longText('statement_snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'date_to', 'id'], 'finance_settlement_customer_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_customer_settlements');
    }
};
