<?php
use App\Database\Migrations\Concerns\HasCreatedUpdatedByColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCreatedUpdatedByColumns;

    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 32)->nullable()->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->tinyInteger('source');
            $table->tinyInteger('status');
            $table->tinyInteger('production_status')->nullable();
            $table->string('customer_name_snapshot', 150);
            $table->string('customer_phone_snapshot', 100);
            $table->foreignId('customer_type_id_snapshot')->constrained('parameter_values')->restrictOnDelete();
            $table->string('customer_type_name_snapshot', 255);
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $this->addCreatedUpdatedByColumns($table);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'created_at']);
            $table->index(['production_status', 'status']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('printing_service_id')->constrained('printing_services')->restrictOnDelete();
            $table->string('service_name_snapshot', 150);
            $table->foreignId('customer_type_id_snapshot')->constrained('parameter_values')->restrictOnDelete();
            $table->string('customer_type_name_snapshot', 255);
            $table->unsignedInteger('quantity');
            $table->text('specifications')->nullable();
            $table->decimal('agreed_total', 12, 2);
            $table->decimal('suggested_total', 12, 2)->nullable();
            $table->string('price_source', 64)->nullable();
            $table->boolean('was_manually_priced')->default(false);
            $table->tinyInteger('production_stage')->default(0);
            $table->foreignId('designer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('printing_location_id')->nullable()->constrained('parameter_values')->restrictOnDelete();
            $this->addCreatedUpdatedByColumns($table);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['order_id', 'deleted_at']);
            $table->index(['printing_service_id', 'deleted_at']);
        });

        Schema::create('order_item_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->string('disk', 32);
            $table->string('path', 1024);
            $table->string('original_name', 255);
            $table->string('extension', 16);
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['order_item_id', 'deleted_at']);
        });

        Schema::create('order_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_daily_sequences', function (Blueprint $table): void {
            $table->date('day')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_daily_sequences');
        Schema::dropIfExists('order_activities');
        Schema::dropIfExists('order_item_files');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
