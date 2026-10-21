<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Models\User;

trait CreatedUpdatedBy
{
    protected static function bootCreatedUpdatedBy(): void
    {
        static::creating(static function (Model $model): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $model->setAttribute('created_by', $userId);
            }
        });

        static::updating(static function (Model $model): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $model->setAttribute('updated_by', $userId);
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
