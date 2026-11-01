<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printing_services', function (Blueprint $table): void {
            $table->decimal('cost_price', 12, 2)->nullable()->after('base_price');
        });
    }

    public function down(): void
    {
        Schema::table('printing_services', function (Blueprint $table): void {
            $table->dropColumn('cost_price');
        });
    }
};
