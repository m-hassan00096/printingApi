<?php

namespace Modules\Identity\Tests\Feature\Roles\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class RoleManagementTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected User $administrator;
    /** @var array<string, Permission> */
    protected array $catalog = [];
    private int $userCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders(['Origin' => 'http://localhost', 'Accept' => 'application/json']);
        app()->setLocale('ar');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->adminRole = $this->makeRole('admin', 'مدير النظام');
        foreach ([
            'identity.roles.view', 'identity.roles.create',
            'identity.roles.update', 'identity.roles.delete',
            'identity.users.view', 'identity.users.create',
            'identity.users.update', 'identity.users.delete',
        ] as $name) {
            $this->catalog[$name] = Permission::findOrCreate($name, 'web');
        }
        $this->adminRole->syncPermissions(array_values($this->catalog));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->administrator = $this->makeUser($this->adminRole);
        $this->loginAs($this->administrator);
    }

    protected function makeRole(
        string $name,
        string $alias,
        RoleStatus $status = RoleStatus::ACTIVE
    ): Role {
        $role = Role::create([
            'name' => $name, 'guard_name' => 'web', 'status' => $status,
        ]);
        $role->translations()->create(['locale' => 'ar', 'alias' => $alias]);
        return $role;
    }

    protected function makeUser(?Role $role = null): User
    {
        $i = ++$this->userCounter;
        $user = User::create([
            'name' => 'Staff '.$i,
            'username' => 'staff_role_test_'.$i,
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
        ]);
        if ($role !== null) {
            $user->assignRole($role);
        }
        return $user;
    }

    protected function loginAs(User $user): void
    {
        // Simulate a fresh authentication context when switching users.
        $this->app['session.store']->forget([
            'password_hash_web',
            'password_hash_sanctum',
        ]);

        $this->actingAs($user, 'web');

        // Prevent Sanctum from reusing the previously authenticated user.
        Auth::guard('sanctum')->forgetUser();
    }
    protected function roleUrl(array $params = []): string
    {
        return '/api/v1/roles'.($params
            ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986)
            : '');
    }

    protected function payload(array $override = []): array
    {
        return array_replace([
            'nameAr' => 'مصمم مطبوعات',
            'status' => RoleStatus::ACTIVE->value,
            'permissionIds' => [
                $this->catalog['identity.users.view']->id,
                $this->catalog['identity.roles.view']->id,
            ],
        ], $override);
    }
}
