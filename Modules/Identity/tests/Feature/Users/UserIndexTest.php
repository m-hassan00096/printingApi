<?php

namespace Modules\Identity\Tests\Feature\Users;

require_once __DIR__.'/Support/UserManagementTestCase.php';

use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Tests\Feature\Users\Support\UserManagementTestCase;

class UserIndexTest extends UserManagementTestCase
{
    public function test_index_uses_users_key_and_returns_pagination(): void
    {
        $this->createManagedUser();
        $this->createManagedUser();

        $this->getJson($this->usersUrl())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.users')
            ->assertJsonMissingPath('data.items')
            ->assertJsonPath('data.pagination.currentPage', 1)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.perPage', 15);
    }

    public function test_pagination_uses_page_and_per_page(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->createManagedUser();
        }

        $this->getJson($this->usersUrl(['page' => 2, 'perPage' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data.users')
            ->assertJsonPath('data.pagination.currentPage', 2)
            ->assertJsonPath('data.pagination.lastPage', 3)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonPath('data.pagination.perPage', 2);
    }

    public function test_search_matches_name(): void
    {
        $found = $this->createManagedUser(['name' => 'Mahmoud Ali']);
        $this->createManagedUser(['name' => 'Hassan Omar']);

        $this->getJson($this->usersUrl(['filter' => ['search' => 'Mahmoud']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.users.0.id', $found->id);
    }

    public function test_search_matches_username(): void
    {
        $found = $this->createManagedUser(['username' => 'production_operator']);
        $this->createManagedUser(['username' => 'sales_agent']);

        $this->getJson($this->usersUrl(['filter' => ['search' => 'production_']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.users.0.id', $found->id);
    }

    public function test_search_matches_phone(): void
    {
        $found = $this->createManagedUser(['phone' => '01012345678']);
        $this->createManagedUser(['phone' => '01198765432']);

        $this->getJson($this->usersUrl(['filter' => ['search' => '0101234']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.users.0.id', $found->id);
    }

    public function test_can_filter_inactive_users_even_when_status_is_zero(): void
    {
        $inactive = $this->createManagedUser(['status' => UserStatus::INACTIVE]);
        $this->createManagedUser();

        $this->getJson($this->usersUrl(['filter' => ['status' => 0]]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.users.0.id', $inactive->id)
            ->assertJsonPath('data.users.0.status', 0);
    }

    public function test_can_filter_users_by_spatie_role_name(): void
    {
        $found = $this->createManagedUser();
        $this->createManagedUser([], $this->adminRole);

        $this->getJson($this->usersUrl(['filter' => ['role' => 'staff']]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.users.0.id', $found->id);
    }

    public function test_can_combine_filters(): void
    {
        $found = $this->createManagedUser([
            'name' => 'Focused User',
            'status' => UserStatus::INACTIVE,
        ]);
        $this->createManagedUser(['name' => 'Focused Active User']);
        $this->createManagedUser(['name' => 'Other', 'status' => UserStatus::INACTIVE]);

        $this->getJson($this->usersUrl(['filter' => [
            'search' => 'Focused',
            'status' => 0,
            'role' => 'staff',
        ]]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.users.0.id', $found->id);
    }

    public function test_default_sort_returns_latest_id_first(): void
    {
        $this->createManagedUser();
        $latest = $this->createManagedUser();

        $this->getJson($this->usersUrl())
            ->assertOk()
            ->assertJsonPath('data.users.0.id', $latest->id);
    }

    public function test_can_sort_users_descending_by_username(): void
    {
        $this->createManagedUser(['username' => 'beta']);
        $this->createManagedUser(['username' => 'zulu']);

        $this->getJson($this->usersUrl(['sort' => '-username']))
            ->assertOk()
            ->assertJsonPath('data.users.0.username', 'zulu');
    }

    public function test_rejects_disallowed_filters(): void
    {
        $this->getJson($this->usersUrl(['filter' => ['password' => 'secret']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    public function test_rejects_disallowed_sort_fields(): void
    {
        $this->getJson($this->usersUrl(['sort' => 'password']))
            ->assertStatus(400);
    }

    public function test_rejects_page_size_over_one_hundred(): void
    {
        $this->getJson($this->usersUrl(['perPage' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('perPage');
    }


    public function test_soft_deleted_users_are_excluded(): void
    {
        $deleted = $this->createManagedUser();
        $deleted->delete();

        $this->createManagedUser();

        $response = $this->getJson($this->usersUrl());

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 2);

        // Get only user IDs from the users collection.
        $userIds = array_column(
            $response->json('data.users'),
            'id'
        );

        // Ensure the deleted user is not returned.
        $this->assertNotContains(
            $deleted->id,
            $userIds
        );

        // Ensure the user was soft deleted, not permanently deleted.
        $this->assertSoftDeleted('users', [
            'id' => $deleted->id,
        ]);
    }

}
