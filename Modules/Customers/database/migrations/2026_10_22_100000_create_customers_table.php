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
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 100);
            // Non-deleted rows store SHA-256 of exact trimmed phone; deleted rows NULL.
            // This is binary-sensitive on MySQL and frees the phone after Soft Delete.
            $table->char('live_phone_hash', 64)->nullable()->unique();
            $table->string('address', 1000)->nullable();
            $table->foreignId('customer_type_id')
                ->constrained('parameter_values')->restrictOnDelete();
            $table->tinyInteger('status');
            $this->addCreatedUpdatedByColumns($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
            $table->index(['customer_type_id', 'deleted_at']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
