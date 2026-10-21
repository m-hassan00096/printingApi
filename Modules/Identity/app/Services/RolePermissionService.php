<?php

namespace Modules\Identity\Services;

use Illuminate\Support\Collection;
use Modules\Identity\Exceptions\Roles\InvalidRolePermissionsException;
use Modules\Identity\Models\Permission;

class RolePermissionService
{
    /** @param array<int, int> $ids @return Collection<int, Permission> */
    public function resolveIds(array $ids): Collection
    {
        $unique = array_values(array_unique(array_map('intval', $ids)));

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $unique)
            ->get();

        if ($permissions->count() !== count($unique)) {
            throw new InvalidRolePermissionsException();
        }

        return $permissions;
    }
}
