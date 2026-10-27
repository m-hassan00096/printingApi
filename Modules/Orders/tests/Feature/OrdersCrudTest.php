<?php
namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class OrdersCrudTest extends OrdersTestCase
{
    public function test_create_draft_has_no_order_number_or_liability_and_preserves_snapshots(): void
    {
        $response = $this->postJson('/api/v1/orders', $this->data());
        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('orders.messages.draft_created'))
            ->assertJsonPath('data', null);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.orderNumber', null)
            ->assertJsonPath('data.productionStatus', null)
            ->assertJsonPath('data.status', OrderStatus::DRAFT->value)
            ->assertJsonPath('data.totalAmount', '350.00')
            ->assertJsonPath('data.items.0.serviceName', 'Business Cards')
            ->assertJsonMissingPath('data.items.0.suggestedTotal')
            ->assertJsonMissingPath('data.items.0.priceSource')
            ->assertJsonMissingPath('data.items.0.wasManuallyPriced')
            ->assertJsonPath('data.items.0.agreedTotal', '350.00')
            ->assertJsonPath('data.items.0.unitPrice', '3.5000');
        $this->assertNull($order->confirmed_at);
        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id, 'event_type' => 'draft_created',
        ]);
    }

    public function test_repeated_idempotency_key_does_not_create_duplicate_drafts(): void
    {
        $this->withHeader('Idempotency-Key', 'draft-duplicate-case');
        $this->postJson('/api/v1/orders', $this->data())
            ->assertCreated()->assertJsonPath('data', null);
        $firstId = Order::query()->max('id');
        $this->postJson('/api/v1/orders', $this->data())
            ->assertCreated()->assertJsonPath('data', null);

        $this->assertSame($firstId, Order::query()->max('id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_activities', 1);
        $this->assertDatabaseCount('order_idempotency_keys', 1);

        $this->postJson('/api/v1/orders', $this->data(['notes' => 'Different input']))
            ->assertStatus(409)
            ->assertJsonPath('message', __('orders.messages.idempotency_reused'));
    }

    public function test_idempotency_keys_are_case_sensitive(): void
    {
        $this->withHeader('Idempotency-Key', 'Example-Key-A')
            ->postJson('/api/v1/orders', $this->data())
            ->assertCreated()->assertJsonPath('data', null);
        $firstId = Order::query()->max('id');
        $this->withHeader('Idempotency-Key', 'example-key-a')
            ->postJson('/api/v1/orders', $this->data())
            ->assertCreated()->assertJsonPath('data', null);

        $this->assertNotSame($firstId, Order::query()->max('id'));
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('order_idempotency_keys', 2);
        $this->assertDatabaseMissing('order_idempotency_keys', [
            'idempotency_key' => 'Example-Key-A',
        ]);
    }

    public function test_same_printing_service_may_appear_twice_with_distinct_specs(): void
    {
        $items = $this->data()['items'];
        $items[] = [
            'serviceId' => $this->service->id,
            'quantity' => 10,
            'specifications' => 'Matte',
            'agreedTotal' => '500.00',
        ];

        $order = $this->draft(['items' => $items]);
        $this->assertSame(2, $order->items()->count());
        $this->assertSame('850.00', (string) $order->total_amount);
    }

    public function test_partial_draft_update_can_change_customer_and_keep_entered_total(): void
    {
        $order = $this->draft();
        $this->service->priceTiers()->create([
            'customer_type_id' => $this->retail->id,
            'min_quantity' => 1, 'max_quantity' => 999,
            'price' => '2.00',
        ]);

        $this->patchJson($this->path($order), [
            'customerId' => $this->secondCustomer->id,
            'notes' => 'New customer',
        ])->assertOk()
            ->assertJsonPath('message', __('orders.messages.draft_updated'))
            ->assertJsonPath('data', null);

        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.customerId', $this->secondCustomer->id)
            ->assertJsonPath('data.items.0.customerTypeId', $this->retail->id)
            ->assertJsonPath('data.items.0.customerTypeName', 'Retail')
            ->assertJsonMissingPath('data.items.0.suggestedTotal')
            ->assertJsonPath('data.items.0.agreedTotal', '350.00');

        $this->assertSame($this->secondCustomer->id, $order->fresh()->customer_id);
        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id, 'event_type' => 'draft_updated',
        ]);
    }

    public function test_catalog_price_or_name_change_does_not_mutate_existing_draft_snapshots(): void
    {
        $order = $this->draft();
        $this->service->base_price = '12.00';
        $this->service->name = 'Cards Renamed';
        $this->service->live_name_hash = hash('sha256', 'Cards Renamed');
        $this->service->save();

        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.items.0.serviceName', 'Business Cards')
            ->assertJsonMissingPath('data.items.0.suggestedTotal')
            ->assertJsonPath('data.items.0.agreedTotal', '350.00');
    }

    public function test_draft_item_update_addition_removal_recalculates_total_and_rejects_last_removal(): void
    {
        $order = $this->draft();
        $firstId = $order->items()->firstOrFail()->id;

        $this->patchJson($this->path($order).'/items/'.$firstId, [
            'agreedTotal' => '400.00',
        ])->assertOk()
            ->assertJsonPath('message', __('orders.messages.item_updated'))
            ->assertJsonPath('data', null);
        $this->assertSame('400.00', (string) $order->fresh()->total_amount);

        $this->deleteJson($this->path($order).'/items/'.$firstId)
            ->assertStatus(409);
        $this->postJson($this->path($order).'/items', [
            'serviceId' => $this->secondService->id,
            'quantity' => 12,
            'agreedTotal' => '240.00',
        ])->assertOk()
            ->assertJsonPath('message', __('orders.messages.item_added'))
            ->assertJsonPath('data', null);
        $this->assertSame('640.00', (string) $order->fresh()->total_amount);

        $this->deleteJson($this->path($order).'/items/'.$firstId)
            ->assertOk()
            ->assertJsonPath('message', __('orders.messages.item_deleted'))
            ->assertJsonPath('data', null);
        $this->assertSame('240.00', (string) $order->fresh()->total_amount);

        $this->assertSoftDeleted('order_items', ['id' => $firstId]);
    }

    public function test_replacing_draft_item_service_keeps_historic_reference(): void
    {
        $order = $this->draft();
        $oldItem = $order->items()->firstOrFail();
        $serviceId = $this->service->id;

        $this->patchJson($this->path($order).'/items/'.$oldItem->id, [
            'serviceId' => $this->secondService->id,
        ])->assertOk()
            ->assertJsonPath('message', __('orders.messages.item_updated'))
            ->assertJsonPath('data', null);

        $newItemId = $order->items()->firstOrFail()->id;
        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.items.0.serviceId', $this->secondService->id)
            ->assertJsonPath('data.totalAmount', '350.00');
        $this->assertNotSame($oldItem->id, $newItemId);
        $this->assertSoftDeleted('order_items', ['id' => $oldItem->id]);
        $this->assertDatabaseHas('order_items', [
            'id' => $oldItem->id, 'printing_service_id' => $serviceId,
        ]);

        $this->deleteJson('/api/v1/printing-services/'.$serviceId)->assertStatus(409);
    }

    public function test_orders_are_priced_only_from_agreed_total_not_catalog_preview(): void
    {
        // PrintingServices' suggestion must not control the actual order price.
        $this->service->base_price = '10000000000.00';
        $this->service->save();

        $this->postJson('/api/v1/orders', $this->data([
            'items' => [[
                'serviceId' => $this->service->id,
                'quantity' => 100,
                'agreedTotal' => '350.00',
            ]],
        ]))->assertCreated()
            ->assertJsonPath('data', null);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->getJson($this->path($order))->assertOk()
            ->assertJsonPath('data.totalAmount', '350.00')
            ->assertJsonPath('data.items.0.agreedTotal', '350.00')
            ->assertJsonPath('data.items.0.unitPrice', '3.5000')
            ->assertJsonMissingPath('data.items.0.suggestedTotal')
            ->assertJsonMissingPath('data.items.0.priceSource')
            ->assertJsonMissingPath('data.items.0.wasManuallyPriced');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('order_items', 'suggested_total'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('order_items', 'price_source'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('order_items', 'was_manually_priced'));
    }

    public function test_rejects_bad_customer_service_date_and_noncanonical_money(): void
    {
        $this->postJson('/api/v1/orders', $this->data([
            'customerId' => 999999,
            'dueDate' => '2020-01-01',
            'items' => [[
                'serviceId' => 99999, 'quantity' => 0, 'agreedTotal' => '12.999',
            ]],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customerId', 'dueDate', 'items.0.serviceId',
                'items.0.quantity', 'items.0.agreedTotal',
            ]);

        $this->postJson('/api/v1/orders', $this->data(['productionStatus' => 3]))
            ->assertUnprocessable()->assertJsonValidationErrors('productionStatus');
    }

    public function test_search_by_customer_phone_name_and_status_and_default_newest(): void
    {
        $first = $this->draft();
        $second = $this->draft(['customerId' => $this->secondCustomer->id]);

        $this->getJson('/api/v1/orders?filter[search]=Mona')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.orders.0.id', $second->id);

        $this->getJson('/api/v1/orders?filter[search]=0101234')->assertOk()
            ->assertJsonPath('data.pagination.total', 1);

        $this->getJson('/api/v1/orders?filter[status]=0')->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.orders.0.id', $second->id);

        $this->getJson('/api/v1/orders?filter[lateOnly]=1')->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->getJson('/api/v1/orders?filter[status]=9')->assertUnprocessable();
        $this->getJson('/api/v1/orders?filter[bad]=1')->assertUnprocessable();
    }

    public function test_late_only_includes_late_deliveries_and_excludes_on_time_deliveries(): void
    {
        $today = \Carbon\CarbonImmutable::now('Africa/Cairo');

        $pending = $this->draft();
        $pending->status = OrderStatus::CONFIRMED;
        $pending->due_date = $today->subDays(2)->format('Y-m-d');
        $pending->production_status = \Modules\Orders\Enums\OrderProductionStatus::NOT_STARTED;
        $pending->save();

        // Cairo's delivered date is authoritative even when UTC calendar day differs.
        $late = $this->draft(['customerId' => $this->secondCustomer->id]);
        $late->status = OrderStatus::CONFIRMED;
        $late->due_date = $today->subDays(4)->format('Y-m-d');
        $late->delivered_at = $today->subDays(3)->setTime(0, 30)->utc();
        $late->delivered_local_date = $today->subDays(3)->format('Y-m-d');
        $late->production_status = \Modules\Orders\Enums\OrderProductionStatus::DELIVERED;
        $late->save();

        $onTime = $this->draft();
        $onTime->status = OrderStatus::CONFIRMED;
        $onTime->due_date = $today->subDays(1)->format('Y-m-d');
        $onTime->delivered_at = $today->subDay()->setTime(10, 0)->utc();
        $onTime->delivered_local_date = $today->subDay()->format('Y-m-d');
        $onTime->production_status = \Modules\Orders\Enums\OrderProductionStatus::DELIVERED;
        $onTime->save();

        $response = $this->getJson('/api/v1/orders?filter[lateOnly]=1')->assertOk()
            ->assertJsonPath('data.pagination.total', 2);

        $ids = array_column($response->json('data.orders'), 'id');
        $this->assertContains($pending->id, $ids);
        $this->assertContains($late->id, $ids);
        $this->assertNotContains($onTime->id, $ids);

        // The Dashboard's "currently overdue" deep link must exclude already-delivered
        // historical lateness, while leaving lateOnly's existing history intact.
        $current = $this->getJson('/api/v1/orders?filter[lateOnly]=1&filter[openOnly]=1')
            ->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->assertSame([$pending->id], array_column($current->json('data.orders'), 'id'));
        $this->getJson('/api/v1/orders?filter[openOnly]=1')
            ->assertOk()->assertJsonPath('data.pagination.total', 1);

        $this->getJson($this->path($late))->assertOk()
            ->assertJsonPath('data.delayStatus', 1);
    }

    public function test_draft_deletion_is_soft_and_only_admin_can_delete(): void
    {
        $order = $this->draft();

        $this->deleteJson($this->path($order))->assertOk();
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertDatabaseCount('order_items', 1);
        $this->getJson($this->path($order))->assertNotFound();
    }

    public function test_arabic_and_english_success_messages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);
            $this->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::uuid())
                ->postJson('/api/v1/orders', $this->data())
                ->assertCreated()
                ->assertJsonPath('message', __('orders.messages.draft_created'));
        }
    }
}
