<?php

namespace Modules\Identity\Tests\Feature\Users\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class UserManagementTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $staffRole;
    protected User $administrator;

    private int $userCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Accept' => 'application/json',
        ]);
        app()->setLocale('ar');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->adminRole = $this->createRole('admin', 'مدير النظام');
        $this->staffRole = $this->createRole('staff', 'موظف');

        $permissions = [];
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permissions[] = Permission::create([
                'name' => 'identity.users.'.$action,
                'guard_name' => 'web',
            ]);
        }

        $this->adminRole->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->administrator = User::create([
            'name' => 'Administrator',
            'username' => 'administrator',
            'password' => 'administrator-password-123',
            'status' => UserStatus::ACTIVE,
        ]);
        $this->administrator->assignRole($this->adminRole);
        $this->actingAs($this->administrator, 'web');
    }

    protected function createRole(
        string $name,
        string $alias,
        RoleStatus $status = RoleStatus::ACTIVE
    ): Role {
        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
            'status' => $status,
        ]);

        $role->translations()->create([
            'locale' => 'ar',
            'alias' => $alias,
        ]);

        return $role;
    }

    protected function createManagedUser(array $attributes = [], ?Role $role = null): User
    {
        $number = ++$this->userCounter;

        $user = User::create(array_replace([
            'name' => 'Managed User '.$number,
            'username' => 'managed_user_'.$number,
            'password' => 'original-password-123',
            'status' => UserStatus::ACTIVE,
        ], $attributes));

        $user->assignRole($role ?? $this->staffRole);

        return $user;
    }

    protected function usersUrl(array $params = []): string
    {
        return '/api/v1/users'.($params
            ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986)
            : '');
    }

    protected function loginAs(User $user): void
    {
        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();
    }

    protected function logoutForTest(): void
    {
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
    }
}
