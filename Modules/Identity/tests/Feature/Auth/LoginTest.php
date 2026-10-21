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

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private const PASSWORD = 'test-password-123';

    protected function setUp(): void
    {
        parent::setUp();

        // Simulate a request from our trusted SPA.
        config()->set('sanctum.stateful', ['localhost']);

        $this->withHeader('Origin', 'http://localhost');

        app()->setLocale('ar');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create a role for the test user.
        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'status' => RoleStatus::ACTIVE,
        ]);

        $role->translations()->create([
            'locale' => 'ar',
            'alias' => 'مدير النظام',
        ]);

        // Give the role one permission.
        $permission = Permission::create([
            'name' => 'identity.users.view',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        // Create a test user with a hashed password.
        $this->user = User::create([
            'name' => 'Test Administrator',
            'username' => 'test_admin',
            'password' => self::PASSWORD,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->user->assignRole($role);
    }

    public function test_active_user_can_login_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'test_admin',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '')
            ->assertJsonPath('data.username', 'test_admin')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.roleAlias', 'مدير النظام')
            ->assertJsonPath('data.status', UserStatus::ACTIVE->value)
            ->assertJsonPath('data.permissions.0', 'identity.users.view')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        // Ensure Laravel authenticated the user via web guard.
        $this->assertAuthenticatedAs($this->user, 'web');

        // Ensure a session cookie was issued.
        $response->assertCookie(config('session.cookie'));

        // SPA login must not create a Sanctum personal access token.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_username_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'unknown_user',
            'password' => self::PASSWORD,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.failed'));

        $this->assertGuest('web');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'test_admin',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.failed'));

        $this->assertGuest('web');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->user->update([
            'status' => UserStatus::INACTIVE,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'test_admin',
            'password' => self::PASSWORD,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.inactive'));

        $this->assertGuest('web');
    }

    public function test_username_is_required(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => self::PASSWORD,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['username']);

        $this->assertGuest('web');
    }

    public function test_password_is_required(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'test_admin',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['password']);

        $this->assertGuest('web');
    }

    public function test_username_is_trimmed_before_login(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => '  test_admin  ',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.username', 'test_admin');

        $this->assertAuthenticatedAs($this->user, 'web');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'username' => 'test_admin',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        // The sixth request must be throttled.
        $this->postJson('/api/v1/auth/login', [
            'username' => 'test_admin',
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->assertGuest('web');
    }
}
