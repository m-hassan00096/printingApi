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
        Schema::create('printing_service_price_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printing_service_id')
                ->constrained('printing_services')->restrictOnDelete();
            // Null means the price applies to every customer type.
            $table->foreignId('customer_type_id')
                ->nullable()->constrained('parameter_values')->restrictOnDelete();
            $table->unsignedBigInteger('min_quantity')->nullable();
            $table->unsignedBigInteger('max_quantity')->nullable();
            $table->decimal('price', 30, 2);
            $this->addCreatedUpdatedByColumns($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['printing_service_id', 'customer_type_id', 'deleted_at'], 'printing_tier_bucket_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printing_service_price_tiers');
    }
};

