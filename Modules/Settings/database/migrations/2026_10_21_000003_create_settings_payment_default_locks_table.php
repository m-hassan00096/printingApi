<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stable rows to lock even when the destination tables are empty.
        // MySQL SELECT ... FOR UPDATE serializes mutations per destination type.
        Schema::create('settings_payment_default_locks', function (Blueprint $table): void {
            $table->string('name', 32)->primary();
        });

        DB::table('settings_payment_default_locks')->insert([
            ['name' => 'bank_accounts'],
            ['name' => 'e_wallets'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_payment_default_locks');
    }
};
