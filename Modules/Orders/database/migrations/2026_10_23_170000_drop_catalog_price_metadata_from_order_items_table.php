<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Incremental change: local databases may already have Orders installed.
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['suggested_total', 'price_source', 'was_manually_priced']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('suggested_total', 12, 2)->nullable();
            $table->string('price_source', 64)->nullable();
            $table->boolean('was_manually_priced')->default(false);
        });
    }
};
