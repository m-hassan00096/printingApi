<?php

namespace Modules\Identity\Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sanctum.stateful', ['localhost']);

        $this->withHeader('Origin', 'http://localhost');

        app()->setLocale('ar');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'status' => RoleStatus::ACTIVE,
        ]);

        $role->translations()->create([
            'locale' => 'ar',
            'alias' => 'مدير النظام',
        ]);

        $permission = Permission::create([
            'name' => 'identity.users.view',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $this->user = User::create([
            'name' => 'Test Administrator',
            'username' => 'test_admin',
            'password' => 'test-password-123',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->user->assignRole($role);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $this->actingAs($this->user, 'web');

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Test Administrator')
            ->assertJsonPath('data.username', 'test_admin')
            ->assertJsonPath('data.status', UserStatus::ACTIVE->value);

        $this->assertAuthenticatedAs($this->user, 'web');
    }

    public function test_guest_cannot_get_profile(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->assertGuest('web');
    }

    public function test_profile_contains_role_and_permissions(): void
    {
        $this->actingAs($this->user, 'web');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.roleAlias', 'مدير النظام')
            ->assertJsonPath(
                'data.permissions.0',
                'identity.users.view'
            );
    }

    public function test_profile_does_not_expose_sensitive_fields(): void
    {
        $this->actingAs($this->user, 'web');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }
}
