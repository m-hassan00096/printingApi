<?php

namespace Modules\Identity\Tests\Feature\Users;

require_once __DIR__.'/Support/UserManagementTestCase.php';

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Models\User;
use Modules\Identity\Tests\Feature\Users\Support\UserManagementTestCase;

class UserDeleteTest extends UserManagementTestCase
{
    public function test_admin_can_soft_delete_user(): void
    {
        $user = $this->createManagedUser();

        $this->deleteJson($this->usersUrl().'/'.$user->id)
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => '',
                'data' => null,
            ]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_deleting_user_preserves_avatar_for_future_restore(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('user.jpg')->store('avatars/users', 'public');
        $user = $this->createManagedUser(['avatar' => $path]);

        $this->deleteJson($this->usersUrl().'/'.$user->id)->assertOk();

        Storage::disk('public')->assertExists($path);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_deleting_unknown_user_returns_not_found(): void
    {
        $this->deleteJson($this->usersUrl().'/999999')->assertNotFound();
    }

    public function test_deleting_same_user_twice_returns_not_found(): void
    {
        $user = $this->createManagedUser();

        $this->deleteJson($this->usersUrl().'/'.$user->id)->assertOk();
        $this->deleteJson($this->usersUrl().'/'.$user->id)->assertNotFound();
    }

    public function test_guest_cannot_delete_user(): void
    {
        $user = $this->createManagedUser();
        $this->logoutForTest();

        $this->deleteJson($this->usersUrl().'/'.$user->id)
            ->assertUnauthorized();

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_user_without_delete_permission_cannot_delete_user(): void
    {
        $user = $this->createManagedUser();
        $this->loginAs($user);

        $this->deleteJson($this->usersUrl().'/'.$user->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }
}
