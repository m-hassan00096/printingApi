<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('collection_id')->nullable()->unique()->constrained('finance_collections')->restrictOnDelete();
            $table->string('receiver_name', 150)->nullable();
            $table->foreignId('delivered_by')->constrained('users')->restrictOnDelete();
            $table->string('delivered_by_name_snapshot', 150);
            $table->timestamp('delivered_at');
            $table->date('delivered_local_date');
            $table->tinyInteger('status')->default(1);
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancelled_by_name_snapshot', 150)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['status', 'delivered_local_date', 'id'], 'deliveries_status_date_id');
            $table->index(['delivered_local_date', 'id']);
        });

        Schema::create('delivery_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation', 40);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->foreignId('result_delivery_id')->nullable()->constrained('deliveries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['actor_id', 'operation', 'key_hash'], 'delivery_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_idempotency_keys');
        Schema::dropIfExists('deliveries');
    }
};
