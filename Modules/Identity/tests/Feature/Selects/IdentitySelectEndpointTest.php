<?php

namespace Modules\Identity\Tests\Feature\Selects;

use App\Services\Select\SelectRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IdentitySelectEndpointTest extends TestCase
{
    use RefreshDatabase;

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
    }

    private function authenticateWithPermissions(array $permissions = []): User
    {
        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'status' => RoleStatus::ACTIVE,
        ]);

        foreach ($permissions as $permission) {
            $record = Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
            $role->givePermissionTo($record);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::create([
            'name' => 'Administrator',
            'username' => 'administrator',
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($role);
        $this->actingAs($user, 'web');
        Auth::guard('sanctum')->forgetUser();

        return $user;
    }

    public function test_identity_module_registers_its_three_selects(): void
    {
        $registry = app(SelectRegistry::class);

        $this->assertNotNull($registry->resolve('users'));
        $this->assertNotNull($registry->resolve('roles'));
        $this->assertNotNull($registry->resolve('permissions'));
    }

    public function test_endpoint_returns_users_roles_and_permissions(): void
    {
        $user = $this->authenticateWithPermissions([
            'identity.users.view',
            'identity.roles.view',
        ]);

        $query = http_build_query([
            'selects' => 'users,roles,permissions',
            'params' => ['roles' => 'active'],
        ], '', '&', PHP_QUERY_RFC3986);

        $this->getJson('/api/v1/selects?'.$query)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.label', 'users')
            ->assertJsonPath('data.0.options.0.value', $user->id)
            ->assertJsonPath('data.1.label', 'roles')
            ->assertJsonPath('data.2.label', 'permissions');
    }

    public function test_endpoint_enforces_permission_per_select(): void
    {
        $this->authenticateWithPermissions(['identity.users.view']);

        $this->getJson('/api/v1/selects?selects=users')->assertOk();
        $this->getJson('/api/v1/selects?selects=roles')->assertForbidden();
        $this->getJson('/api/v1/selects?selects=permissions')->assertForbidden();
    }

    public function test_endpoint_rejects_unsupported_provider_filter(): void
    {
        $this->authenticateWithPermissions(['identity.roles.view']);

        $query = http_build_query([
            'selects' => 'roles',
            'params' => ['roles' => 'inactive'],
        ], '', '&', PHP_QUERY_RFC3986);

        $this->getJson('/api/v1/selects?'.$query)
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }
}
