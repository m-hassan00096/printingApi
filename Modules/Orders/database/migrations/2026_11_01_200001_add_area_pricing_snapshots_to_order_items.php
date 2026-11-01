<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('pricing_method', 32)->default('quantity')->after('printing_service_id');
            $table->decimal('width_cm', 12, 4)->nullable()->after('quantity');
            $table->decimal('height_cm', 12, 4)->nullable()->after('width_cm');
            $table->decimal('pricing_rate_snapshot', 34, 6)->nullable()->after('height_cm');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['pricing_method', 'width_cm', 'height_cm', 'pricing_rate_snapshot']);
        });
    }
};
