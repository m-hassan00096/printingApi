<?php

namespace Modules\Identity\Tests\Feature\Users;

require_once __DIR__.'/Support/UserManagementTestCase.php';

use Illuminate\Support\Facades\Hash;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Identity\Tests\Feature\Users\Support\UserManagementTestCase;

class UserStoreTest extends UserManagementTestCase
{
    private function validPayload(array $changes = []): array
    {
        return array_replace([
            'name' => 'Ahmed Ali',
            'username' => 'new_employee',
            'phone' => '01000000001',
            'address' => 'Cairo',
            'status' => UserStatus::ACTIVE->value,
            'roleId' => $this->staffRole->id,
            'password' => 'new-password-123',
            'passwordConfirmation' => 'new-password-123',
        ], $changes);
    }

    public function test_admin_can_create_user_with_exactly_one_role(): void
    {
        $this->postJson($this->usersUrl(), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.username', 'new_employee')
            ->assertJsonPath('data.status', UserStatus::ACTIVE->value)
            ->assertJsonPath('data.role.id', $this->staffRole->id)
            ->assertJsonPath('data.role.alias', 'موظف')
            ->assertJsonMissingPath('data.password');

        $user = User::where('username', 'new_employee')->firstOrFail();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertNotSame('new-password-123', $user->password);
        $this->assertSame(['staff'], $user->getRoleNames()->all());
    }

    public function test_user_defaults_to_active_when_status_is_not_sent(): void
    {
        $data = $this->validPayload();
        unset($data['status']);

        $this->postJson($this->usersUrl(), $data)
            ->assertCreated()
            ->assertJsonPath('data.status', UserStatus::ACTIVE->value);

        $this->assertSame(
            UserStatus::ACTIVE,
            User::where('username', 'new_employee')->firstOrFail()->status
        );
    }

    public function test_admin_can_create_inactive_user(): void
    {
        $this->postJson($this->usersUrl(), $this->validPayload(['status' => 0]))
            ->assertCreated()
            ->assertJsonPath('data.status', 0);

        $this->assertSame(
            UserStatus::INACTIVE,
            User::where('username', 'new_employee')->firstOrFail()->status
        );
    }

    public function test_duplicate_username_is_rejected(): void
    {
        $this->createManagedUser(['username' => 'new_employee']);

        $this->postJson($this->usersUrl(), $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $this->createManagedUser(['phone' => '01000000001']);

        $this->postJson($this->usersUrl(), $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertDatabaseMissing('users', ['username' => 'new_employee']);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->postJson($this->usersUrl(), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'username', 'password', 'roleId']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->postJson($this->usersUrl(), $this->validPayload([
            'passwordConfirmation' => 'different-password-123',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('passwordConfirmation');

        $this->assertDatabaseMissing('users', ['username' => 'new_employee']);
    }

    public function test_status_must_be_valid(): void
    {
        $this->postJson($this->usersUrl(), $this->validPayload(['status' => 10]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_cannot_assign_an_inactive_role(): void
    {
        $inactive = $this->createRole('disabled-role', 'دور معطل', RoleStatus::INACTIVE);

        $this->postJson($this->usersUrl(), $this->validPayload([
            'roleId' => $inactive->id,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roleId');

        $this->assertDatabaseMissing('users', ['username' => 'new_employee']);
    }

    public function test_cannot_assign_unknown_role(): void
    {
        $this->postJson($this->usersUrl(), $this->validPayload(['roleId' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roleId');
    }

    public function test_guest_cannot_create_user(): void
    {
        $this->logoutForTest();

        $this->postJson($this->usersUrl(), $this->validPayload())
            ->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['username' => 'new_employee']);
    }

    public function test_user_without_create_permission_cannot_create_user(): void
    {
        $this->loginAs($this->createManagedUser());

        $this->postJson($this->usersUrl(), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'new_employee']);
    }
}
