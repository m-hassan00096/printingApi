<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Intent only: these values MUST NOT create financial movements.
            $table->decimal('planned_deposit_amount', 12, 2)->nullable();
            $table->tinyInteger('planned_payment_method')->nullable();
            $table->unsignedBigInteger('planned_payment_destination_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'planned_deposit_amount',
                'planned_payment_method',
                'planned_payment_destination_id',
            ]);
        });
    }
};
