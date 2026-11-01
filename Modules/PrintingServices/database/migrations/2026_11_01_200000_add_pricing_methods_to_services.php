<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printing_services', function (Blueprint $table): void {
            $table->string('pricing_method', 32)->default('quantity')->after('name');
            $table->decimal('base_price', 34, 6)->nullable()->change();
        });

        Schema::table('printing_service_price_tiers', function (Blueprint $table): void {
            $table->decimal('price', 34, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('printing_service_price_tiers', function (Blueprint $table): void {
            $table->decimal('price', 30, 2)->change();
        });
        Schema::table('printing_services', function (Blueprint $table): void {
            $table->decimal('base_price', 30, 2)->nullable()->change();
            $table->dropColumn('pricing_method');
        });
    }
};
