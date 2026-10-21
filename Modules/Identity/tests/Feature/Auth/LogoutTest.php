<?php

namespace Modules\Identity\Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Simulate a trusted SPA request.
        config()->set('sanctum.stateful', ['localhost']);

        $this->withHeader('Origin', 'http://localhost');

        // Create an active role.
        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'status' => RoleStatus::ACTIVE,
        ]);

        // Create a test user.
        $this->user = User::create([
            'name' => 'Test Administrator',
            'username' => 'logout_admin',
            'password' => 'test-password-123',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->user->assignRole($role);
    }

    public function test_authenticated_user_can_logout(): void
    {
        // Arrange: authenticate using the web session guard.
        $this->actingAs($this->user, 'web');

        $this->assertAuthenticatedAs($this->user, 'web');

        // Act: send logout request.
        $response = $this->postJson('/api/v1/auth/logout');

        // Assert: successful response.
        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => '',
                'data' => null,
            ]);

        // Assert: the user is no longer authenticated.
        $this->assertGuest('web');
    }

    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();

        $this->assertGuest('web');
    }


public function test_user_cannot_logout_twice(): void
{
    // Arrange: authenticate the user.
    $this->actingAs($this->user, 'web');

    // First logout succeeds.
    $this->postJson('/api/v1/auth/logout')
        ->assertOk();

    // Verify the web session is unauthenticated.
    $this->assertGuest('web');

    // Clear the cached Sanctum user between test requests.
    Auth::guard('sanctum')->forgetUser();

    // Verify Sanctum no longer recognizes the user.
    $this->assertGuest('sanctum');

    // Second logout must be rejected.
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
}
}
