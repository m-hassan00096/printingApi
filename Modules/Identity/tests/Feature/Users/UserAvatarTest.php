<?php

namespace Modules\Identity\Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config()->set('sanctum.stateful', ['localhost']);
        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Accept' => 'application/json',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'status' => RoleStatus::ACTIVE,
        ]);

        foreach (['identity.users.create', 'identity.users.update'] as $name) {
            $permission = Permission::create([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $this->role->givePermissionTo($permission);
        }

        $this->administrator = User::create([
            'name' => 'Administrator',
            'username' => 'administrator',
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->administrator->assignRole($this->role);
        $this->actingAs($this->administrator, 'web');
    }

    public function test_it_uploads_avatar_on_user_creation(): void
    {
        $response = $this->post('/api/v1/users', [
            'name' => 'Photo User',
            'username' => 'photo_user',
            'password' => 'password-123',
            'passwordConfirmation' => 'password-123',
            'roleId' => $this->role->id,
            'avatar' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $user = User::where('username', 'photo_user')->firstOrFail();
        $path = $user->getRawOriginal('avatar');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $user->avatar);
        $this->assertSame($user->avatar, $response->json('data.avatar'));
    }

    public function test_it_replaces_old_avatar_when_new_one_is_uploaded(): void
    {
        $oldPath = UploadedFile::fake()->image('old.jpg')->store('avatars/users', 'public');

        $user = User::create([
            'name' => 'Old Photo User',
            'username' => 'old_photo_user',
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
            'avatar' => $oldPath,
        ]);
        $user->assignRole($this->role);

        // PHP parses multipart file uploads as POST; method spoofing handles PATCH.
        $this->post('/api/v1/users/'.$user->id, [
            '_method' => 'PATCH',
            'avatar' => UploadedFile::fake()->image('new.png'),
        ])->assertOk();

        $user->refresh();
        $newPath = $user->getRawOriginal('avatar');
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        $this->assertSame(Storage::disk('public')->url($newPath), $user->avatar);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_avatar_accessor_returns_url_and_mutator_stores_relative_path(): void
    {
        $user = User::create([
            'name' => 'Accessor User',
            'username' => 'accessor_user',
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
            'avatar' => '/avatars/users/example.webp',
        ]);

        $this->assertSame('avatars/users/example.webp', $user->getRawOriginal('avatar'));
        $this->assertSame(
            Storage::disk('public')->url('avatars/users/example.webp'),
            $user->avatar
        );
    }

    public function test_it_rejects_unsupported_file_types(): void
    {
        $this->post('/api/v1/users', [
            'name' => 'Invalid Photo User',
            'username' => 'invalid_photo_user',
            'password' => 'password-123',
            'passwordConfirmation' => 'password-123',
            'roleId' => $this->role->id,
            'avatar' => UploadedFile::fake()->create('avatar.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertDatabaseMissing('users', ['username' => 'invalid_photo_user']);
    }

    public function test_it_rejects_avatar_larger_than_five_megabytes(): void
    {
        $this->post('/api/v1/users', [
            'name' => 'Large Photo User',
            'username' => 'large_photo_user',
            'password' => 'password-123',
            'passwordConfirmation' => 'password-123',
            'roleId' => $this->role->id,
            'avatar' => UploadedFile::fake()->image('too-large.jpg')->size(5121),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->assertDatabaseMissing('users', ['username' => 'large_photo_user']);
    }
}
