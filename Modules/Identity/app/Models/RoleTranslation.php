<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleTranslation extends Model
{
    protected $fillable = [
        'role_id',
        'locale',
        'alias',
        'description',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
