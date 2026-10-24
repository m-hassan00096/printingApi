<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_item_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name_snapshot', 150);
            $table->tinyInteger('event_type');
            $table->tinyInteger('from_stage')->nullable();
            $table->tinyInteger('to_stage')->nullable();
            $table->foreignId('old_printing_location_id')->nullable()->constrained('parameter_values')->restrictOnDelete();
            $table->foreignId('new_printing_location_id')->nullable()->constrained('parameter_values')->restrictOnDelete();
            $table->string('old_printing_location_name', 255)->nullable();
            $table->string('new_printing_location_name', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_item_id', 'id']);
            $table->index(['actor_id', 'event_type', 'order_item_id'], 'production_actor_event_item');
        });

        Schema::create('production_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation', 64);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->unique(['actor_id', 'operation', 'key_hash'], 'production_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_idempotency_keys');
        Schema::dropIfExists('production_item_events');
    }
};
