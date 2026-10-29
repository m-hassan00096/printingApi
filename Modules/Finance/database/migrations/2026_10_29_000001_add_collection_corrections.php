<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_collections', function (Blueprint $table): void {
            $table->foreignId('corrected_from_id')->nullable()->unique()
                ->constrained('finance_collections')->restrictOnDelete();
            $table->foreignId('corrected_to_id')->nullable()->unique()
                ->constrained('finance_collections')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_collections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('corrected_to_id');
            $table->dropConstrainedForeignId('corrected_from_id');
        });
    }
};
