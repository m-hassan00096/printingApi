<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_collections', function (Blueprint $table): void {
            $table->string('actual_receipt_number', 40)->nullable()->after('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('finance_collections', function (Blueprint $table): void {
            $table->dropColumn('actual_receipt_number');
        });
    }
};
