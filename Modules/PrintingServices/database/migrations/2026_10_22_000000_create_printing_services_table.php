<?php

use App\Database\Migrations\Concerns\HasCreatedUpdatedByColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCreatedUpdatedByColumns;

    public function up(): void
    {
        Schema::create('printing_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            // SHA-256 of the canonical UTF-8 name while live; NULL after soft delete.
            // Unlike a name/deleted_at composite, this is case-sensitive on MySQL
            // and permits an exact name to be reused after soft deletion.
            $table->char('live_name_hash', 64)->nullable()->unique();
            $table->decimal('base_price', 30, 2)->nullable();
            $table->tinyInteger('status');
            $this->addCreatedUpdatedByColumns($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printing_services');
    }
};

