<?php

namespace App\Database\Migrations\Concerns;

use Illuminate\Database\Schema\Blueprint;

trait HasCreatedUpdatedByColumns
{
    protected function addCreatedUpdatedByColumns(Blueprint $table): void
    {
        $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->restrictOnDelete();

        $table->foreignId('updated_by')
            ->nullable()
            ->constrained('users')
            ->restrictOnDelete();
    }
}
