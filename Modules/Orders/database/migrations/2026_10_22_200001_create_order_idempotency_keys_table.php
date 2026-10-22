<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation', 48);
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['actor_id', 'operation', 'idempotency_key'], 'orders_idempotency_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_idempotency_keys');
    }
};
