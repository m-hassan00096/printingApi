<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_account_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('settlement_number', 64)->nullable()->unique();
            $table->unsignedBigInteger('customer_id')->index();
            $table->date('scope_to');
            $table->date('settled_on');
            $table->string('status', 24);
            $table->decimal('balance_before', 30, 2);
            $table->decimal('collected_amount', 12, 2);
            $table->decimal('balance_after', 30, 2);
            $table->decimal('account_balance_after', 30, 2);
            $table->unsignedBigInteger('collection_id')->nullable()->unique();
            $table->char('preview_hash', 64);
            $table->char('after_hash', 64)->nullable();
            $table->json('snapshot');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at');
            $table->unique(['customer_id', 'preview_hash'], 'finance_settlement_customer_preview_unique');
            $table->index(['customer_id', 'scope_to', 'id'], 'finance_settlement_customer_scope_index');
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('collection_id')->references('id')->on('finance_collections')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Dropping this evidence table is not a financial reversal.
        Schema::dropIfExists('finance_account_settlements');
    }
};
