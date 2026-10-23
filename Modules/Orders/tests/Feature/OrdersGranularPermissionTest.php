<?php

namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrdersGranularPermissionTest extends OrdersTestCase
{
    private function asClerk(): User
    {
        $clerk = User::create([
            'name' => 'Limited Order Clerk',
            'username' => 'limited_order_clerk',
            'password' => 'password123',
            'status' => UserStatus::ACTIVE,
        ]);

        $clerk->assignRole($this->staffRole);
        $this->login($clerk);

        return $clerk;
    }

    private function grant(string ...$names): void
    {
        $this->staffRole->givePermissionTo(
            array_map(
                fn (string $name) => Permission::findOrCreate($name, 'web'),
                $names,
            ),
        );
    }

    public function test_listing_orders_does_not_grant_order_detail_or_activity_access(): void
    {
        $order = $this->draft();
        $this->asClerk();

        $this->getJson('/api/v1/orders')->assertForbidden();
        $this->getJson($this->path($order))->assertForbidden();
        $this->getJson($this->path($order).'/activities')->assertForbidden();

        $this->grant('orders.list');
        $this->getJson('/api/v1/orders')->assertOk();
        $this->getJson($this->path($order))->assertForbidden();
        $this->getJson($this->path($order).'/activities')->assertForbidden();

        $this->grant('orders.view');
        $this->getJson($this->path($order))->assertOk();
        $this->getJson($this->path($order).'/activities')->assertForbidden();

        $this->grant('orders.activities.view');
        $this->getJson($this->path($order).'/activities')->assertOk();
    }

    public function test_item_mutations_require_their_own_independent_permissions(): void
    {
        $order = $this->draft();
        $firstItem = $order->items()->firstOrFail();
        $base = $this->path($order).'/items';

        $this->asClerk();
        $this->grant('orders.update');

        $payload = [
            'serviceId' => $this->secondService->id,
            'quantity' => 25,
            'agreedTotal' => '250.00',
        ];

        $this->postJson($base, $payload)->assertForbidden();
        $this->patchJson($base.'/'.$firstItem->id, ['quantity' => 10])->assertForbidden();
        $this->deleteJson($base.'/'.$firstItem->id)->assertForbidden();

        $this->grant('orders.items.create');
        $this->postJson($base, $payload)->assertOk();
        $newItem = $order->items()->latest('id')->firstOrFail();

        $this->patchJson($base.'/'.$newItem->id, ['quantity' => 11])->assertForbidden();
        $this->grant('orders.items.update');
        $this->patchJson($base.'/'.$newItem->id, ['quantity' => 11])->assertOk();

        $this->deleteJson($base.'/'.$newItem->id)->assertForbidden();
        $this->grant('orders.items.delete');
        $this->deleteJson($base.'/'.$newItem->id)->assertOk();
    }

    public function test_file_permissions_are_independent_for_upload_list_view_and_delete(): void
    {
        Storage::fake('local');
        $order = $this->draft();
        $item = $order->items()->firstOrFail();
        $base = $this->path($order).'/items/'.$item->id.'/files';
        $upload = static fn () => ['file' => UploadedFile::fake()->createWithContent('design.pdf', '%PDF-1.7 test')];

        $this->asClerk();
        $this->grant('orders.update');

        $this->post($base, $upload(), ['Accept' => 'application/json'])->assertForbidden();
        $this->getJson($base)->assertForbidden();

        $this->grant('orders.files.upload');
        $id = $this->post($base, $upload(), ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        $this->getJson($base)->assertForbidden();
        $this->get($base.'/'.$id)->assertForbidden();
        $this->deleteJson($base.'/'.$id)->assertForbidden();

        $this->grant('orders.files.list');
        $this->getJson($base)->assertOk()->assertJsonPath('data.0.url', null);

        $this->grant('orders.files.view');
        $this->get($base.'/'.$id)->assertOk();
        $this->assertNotNull($this->getJson($base)->json('data.0.url'));

        $this->grant('orders.files.delete');
        $this->deleteJson($base.'/'.$id)->assertOk();
    }
}
