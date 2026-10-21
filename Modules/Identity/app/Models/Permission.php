<?php

namespace Modules\Identity\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    public function translations(): HasMany
    {
        return $this->hasMany(PermissionTranslation::class);
    }
}
