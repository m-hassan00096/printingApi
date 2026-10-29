<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Identity\Enums\RoleStatus;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoleStatus::class,
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(RoleTranslation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }
}
