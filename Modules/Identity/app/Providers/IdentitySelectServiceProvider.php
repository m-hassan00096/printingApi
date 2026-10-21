<?php

namespace Modules\Identity\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Services\Selects\UserSelectService;
use Modules\Identity\Services\Selects\RoleSelectService;
use Modules\Identity\Services\Selects\PermissionSelectService;

class IdentitySelectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SelectRegistry::register([
            'users' => [
                UserSelectService::class,
                'getAllUsers',
                'identity.users.view',
            ],
            'roles' => [
                RoleSelectService::class,
                'getAllRoles',
                'identity.roles.view',
            ],
            'permissions' => [
                PermissionSelectService::class,
                'getAllPermissions',
                'identity.roles.view',
            ],
        ]);
    }
}
