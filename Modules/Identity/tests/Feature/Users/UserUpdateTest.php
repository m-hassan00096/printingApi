<?php

namespace Modules\Identity\Tests\Feature\Users;

require_once __DIR__.'/Support/UserManagementTestCase.php';

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Tests\Feature\Users\Support\UserManagementTestCase;

class UserUpdateTest extends UserManagementTestCase
{
    public function test_can_update_selected_fields_without_overwriting_other_fields(): void
    {
        $user = $this->createManagedUser([
            'name' => 'Old Name',
            'username' => 'unchanged_username',
            'phone' => '01022222222',
        ]);

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'name' => 'Updated Name',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.username', 'unchanged_username')
            ->assertJsonPath('data.phone', '01022222222');

        $user->refresh();
        $this->assertTrue(Hash::check('original-password-123', $user->password));
        $this->assertSame(['staff'], $user->getRoleNames()->all());
    }

    public function test_can_update_user_status_to_inactive(): void
    {
        $user = $this->createManagedUser();

        $this->patchJson($this->usersUrl().'/'.$user->id, ['status' => 0])
            ->assertOk()
            ->assertJsonPath('data.status', 0);

        $this->assertSame(UserStatus::INACTIVE, $user->fresh()->status);
    }

    public function test_can_change_role_and_keep_exactly_one_role(): void
    {
        $user = $this->createManagedUser();
        $reviewer = $this->createRole('reviewer', 'مراجع');

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'roleId' => $reviewer->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.role.id', $reviewer->id)
            ->assertJsonPath('data.role.name', 'reviewer');

        $this->assertSame(['reviewer'], $user->fresh()->getRoleNames()->all());
    }

    public function test_can_change_password_with_valid_confirmation(): void
    {
        $user = $this->createManagedUser();

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'password' => 'changed-password-123',
            'passwordConfirmation' => 'changed-password-123',
        ])
            ->assertOk()
            ->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('changed-password-123', $user->fresh()->password));
    }

    public function test_original_username_can_be_sent_unchanged(): void
    {
        $user = $this->createManagedUser();

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'username' => $user->username,
        ])->assertOk();
    }

    public function test_cannot_use_another_users_username(): void
    {
        $user = $this->createManagedUser();
        $other = $this->createManagedUser();

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'username' => $other->username,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');

        $this->assertSame($user->username, $user->fresh()->username);
    }

    public function test_cannot_use_another_users_phone(): void
    {
        $user = $this->createManagedUser(['phone' => '01011111111']);
        $other = $this->createManagedUser(['phone' => '01033333333']);

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'phone' => $other->phone,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $user = $this->createManagedUser();

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'password' => 'changed-password-123',
            'passwordConfirmation' => 'wrong-password-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('passwordConfirmation');

        $this->assertTrue(Hash::check('original-password-123', $user->fresh()->password));
    }

    public function test_role_must_be_active(): void
    {
        $user = $this->createManagedUser();
        $inactiveRole = $this->createRole('inactive-reviewer', 'مراجع معطل', RoleStatus::INACTIVE);

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'roleId' => $inactiveRole->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roleId');

        $this->assertSame(['staff'], $user->fresh()->getRoleNames()->all());
    }

    public function test_update_without_new_avatar_preserves_existing_file(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('existing.webp')->store('avatars/users', 'public');
        $user = $this->createManagedUser(['avatar' => $path]);

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'name' => 'Updated Without New Photo',
        ])->assertOk();

        $this->assertSame($path, $user->fresh()->getRawOriginal('avatar'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_unknown_user_cannot_be_updated(): void
    {
        $this->patchJson($this->usersUrl().'/999999', [
            'name' => 'Nobody',
        ])->assertNotFound();
    }

    public function test_deleted_user_cannot_be_updated(): void
    {
        $user = $this->createManagedUser();
        $user->delete();

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'name' => 'Should Fail',
        ])->assertNotFound();
    }

    public function test_guest_cannot_update_users(): void
    {
        $user = $this->createManagedUser();
        $this->logoutForTest();

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'name' => 'Should Fail',
        ])->assertUnauthorized();
    }

    public function test_user_without_update_permission_cannot_update_users(): void
    {
        $user = $this->createManagedUser();
        $this->loginAs($user);

        $this->patchJson($this->usersUrl().'/'.$user->id, [
            'name' => 'Should Fail',
        ])->assertForbidden();
    }
}
