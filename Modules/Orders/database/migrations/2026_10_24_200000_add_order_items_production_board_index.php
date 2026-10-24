<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders owns the item table; production reads it by stage and order.
        Schema::table('order_items', function (Blueprint $table): void {
            $table->index(['production_stage', 'order_id'], 'order_items_stage_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_stage_order_index');
        });
    }
};
