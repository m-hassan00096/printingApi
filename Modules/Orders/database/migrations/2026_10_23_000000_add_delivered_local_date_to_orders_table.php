<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the original Orders migration unchanged for existing local DBs.
        Schema::table('orders', function (Blueprint $table): void {
            $table->date('delivered_local_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('delivered_local_date');
        });
    }
};
