<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_collections', function (Blueprint $table): void {
            $table->id();
            $table->string('receipt_number', 40)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->tinyInteger('source');
            $table->decimal('amount', 12, 2);
            $table->tinyInteger('payment_method');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('e_wallet_id')->nullable()->constrained('e_wallets')->restrictOnDelete();
            $table->string('destination_snapshot', 255)->nullable();
            $table->date('collected_on');
            $table->text('note')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'collected_on']);
            $table->index(['collected_on', 'status']);
        });

        Schema::create('finance_refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('refund_number', 40)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->tinyInteger('payment_method');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('e_wallet_id')->nullable()->constrained('e_wallets')->restrictOnDelete();
            $table->string('destination_snapshot', 255)->nullable();
            $table->date('refunded_on');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'refunded_on']);
        });

        Schema::create('finance_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->tinyInteger('type');
            $table->decimal('signed_amount', 12, 2);
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained('finance_collections')->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained('finance_refunds')->restrictOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('finance_ledger_entries')->restrictOnDelete();
            $table->string('event_key', 160)->unique();
            $table->date('effective_date');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'effective_date', 'id'], 'finance_ledger_customer_date');
        });

        Schema::create('finance_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation', 48);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('result_type', 40)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->timestamps();
            $table->unique(['actor_id', 'operation', 'key_hash'], 'finance_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_idempotency_keys');
        Schema::dropIfExists('finance_ledger_entries');
        Schema::dropIfExists('finance_refunds');
        Schema::dropIfExists('finance_collections');
    }
};
