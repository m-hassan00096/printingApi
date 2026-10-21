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
        Schema::create('parameter_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parameter_id')
                ->constrained('parameters')
                ->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->tinyInteger('status');
            $this->addCreatedUpdatedByColumns($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parameter_id', 'name']);
            $table->index(['parameter_id', 'status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_values');
    }
};
