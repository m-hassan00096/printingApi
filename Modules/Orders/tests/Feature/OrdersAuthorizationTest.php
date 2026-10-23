<?php
namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Illuminate\Support\Facades\Auth;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrdersAuthorizationTest extends OrdersTestCase
{
    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->flushSession();
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();

        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->postJson('/api/v1/orders', $this->data())->assertUnauthorized();
    }

    public function test_only_delete_permission_is_required_regardless_of_role(): void
    {
        $order = $this->draft();
        $staff = User::create([
            'name' => 'Staff', 'username' => 'orders_staff_test',
            'password' => 'password123', 'status' => UserStatus::ACTIVE,
        ]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);

        $this->getJson('/api/v1/orders')->assertForbidden();
        $this->postJson('/api/v1/orders', $this->data())->assertForbidden();

        // Staff without permission cannot delete, but the same non-admin
        // user can delete a Draft as soon as orders.delete is granted.
        $this->deleteJson($this->path($order))->assertForbidden();
        $this->staffRole->givePermissionTo(
            \Modules\Identity\Models\Permission::findOrCreate('orders.delete', 'web'),
        );
        $this->deleteJson($this->path($order))->assertOk()
            ->assertJsonPath('message', __('orders.messages.draft_deleted'));
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }
}
