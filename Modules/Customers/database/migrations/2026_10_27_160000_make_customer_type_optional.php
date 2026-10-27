<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', static function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_type_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('customers')->whereNull('customer_type_id')->exists()) {
            throw new \RuntimeException('Cannot make customer type mandatory while unclassified customers exist.');
        }

        Schema::table('customers', static function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_type_id')->nullable(false)->change();
        });
    }
};
