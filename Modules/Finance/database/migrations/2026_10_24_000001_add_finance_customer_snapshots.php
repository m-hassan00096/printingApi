<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['finance_collections', 'finance_refunds'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('customer_name_snapshot', 150)->nullable();
                $blueprint->string('customer_phone_snapshot', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['finance_collections', 'finance_refunds'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn([
                'customer_name_snapshot', 'customer_phone_snapshot',
            ]));
        }
    }
};
