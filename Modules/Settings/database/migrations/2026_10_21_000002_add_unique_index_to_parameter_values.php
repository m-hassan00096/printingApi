<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parameter_values', function (Blueprint $table): void {
            $table->dropIndex(['parameter_id', 'name']);

            // Intentionally includes soft-deleted rows: names are reserved permanently
            // within a group, while different groups may share the same name.
            $table->unique(['parameter_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('parameter_values', function (Blueprint $table): void {
            $table->dropUnique(['parameter_id', 'name']);
            $table->index(['parameter_id', 'name']);
        });
    }
};
