<?php

namespace Modules\Identity\Actions\Roles;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Exceptions\Roles\ProtectedRoleException;
use Modules\Identity\Exceptions\Roles\RoleHasUsersException;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;

class DeleteRoleAction
{
    public function execute(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $role = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($role->guard_name === 'web', 404);

            if ($role->name === 'admin') {
                throw new ProtectedRoleException();
            }

            // Check actual assignments in the pivot, including soft-deleted users.
            // The User relation alone can exclude soft-deleted employees.
            $hasUsers = DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))
                ->where('role_id', $role->getKey())
                ->where('model_type', (new User())->getMorphClass())
                ->exists();

            if ($hasUsers) {
                throw new RoleHasUsersException();
            }

            $role->translations()->delete();
            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
