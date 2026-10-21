<?php

namespace Modules\Identity\Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Http\Resources\UserCollection;
use Modules\Identity\Models\User;
use Tests\TestCase;

class UserCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_uses_users_key_instead_of_items(): void
    {
        User::create([
            'name' => 'Test User',
            'username' => 'collection_test_user',
            'password' => 'password-123',
            'status' => UserStatus::ACTIVE,
        ]);

        $users = User::with('roles.translations')->paginate(15);
        $payload = (new UserCollection($users))->resolve(Request::create('/api/v1/users'));

        $this->assertArrayHasKey('users', $payload);
        $this->assertArrayNotHasKey('items', $payload);
        $this->assertCount(1, $payload['users']);
        $this->assertSame(1, $payload['pagination']['total']);
    }
}
