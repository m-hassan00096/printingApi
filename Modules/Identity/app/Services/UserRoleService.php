<?php

namespace Modules\Identity\Services;

use Modules\Identity\Exceptions\Users\InvalidUserRoleException;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;

class UserRoleService
{
    public function assign(User $user, int $roleId): void
    {
        $role = Role::query()
            ->whereKey($roleId)
            ->where('guard_name', 'web')
            ->where('status', RoleStatus::ACTIVE->value)
            ->first();

        if (! $role) {
            throw new InvalidUserRoleException();
        }

        // Exactly one role for every managed user.
        $user->syncRoles([$role]);
    }
}
