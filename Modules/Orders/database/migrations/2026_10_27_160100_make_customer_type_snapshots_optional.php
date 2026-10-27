<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_type_id_snapshot')->nullable()->change();
            $table->string('customer_type_name_snapshot', 255)->nullable()->change();
        });
        Schema::table('order_items', static function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_type_id_snapshot')->nullable()->change();
            $table->string('customer_type_name_snapshot', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        foreach (['orders', 'order_items'] as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->whereNull('customer_type_id_snapshot')->exists()) {
                throw new \RuntimeException('Cannot restore required type snapshots while unclassified orders exist.');
            }
        }
        Schema::table('order_items', static function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_type_id_snapshot')->nullable(false)->change();
            $table->string('customer_type_name_snapshot', 255)->nullable(false)->change();
        });
        Schema::table('orders', static function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_type_id_snapshot')->nullable(false)->change();
            $table->string('customer_type_name_snapshot', 255)->nullable(false)->change();
        });
    }
};
