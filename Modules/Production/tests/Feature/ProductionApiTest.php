<?php
namespace Modules\Production\Tests\Feature;

require_once __DIR__.'/../../../Orders/tests/Feature/Support/OrdersTestCase.php';

use Illuminate\Support\Str;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Orders\Enums\OrderItemStage;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\Parameter;
use Modules\Settings\Models\ParameterValue;
use Spatie\Permission\PermissionRegistrar;

class ProductionApiTest extends OrdersTestCase
{
    private ParameterValue $printer;
    private ParameterValue $secondPrinter;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'production.items.list', 'production.summary.view', 'production.history.view',
            'production.design.start', 'production.design.complete',
            'production.printing.send', 'production.printing.change_location',
            'production.printing.complete', 'production.items.undo',
        ] as $permission) {
            $this->adminRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $param = Parameter::query()->firstOrCreate(['name' => 'printing_locations']);
        $this->printer = $param->values()->create(['name' => 'Local Press', 'status' => ParameterValueStatus::ACTIVE]);
        $this->secondPrinter = $param->values()->create(['name' => 'Vendor Press', 'status' => ParameterValueStatus::ACTIVE]);
    }

    private function confirmed(array $data = []): Order
    {
        $order = $this->draft($data);
        $this->postJson($this->path($order).'/confirm', [])
            ->assertOk();
        return $order->fresh();
    }

    private function item(Order $order): int
    {
        return $order->items()->firstOrFail()->id;
    }

    private function action(int $id, string $action, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/production/items/$id/$action", $body);
    }

    private function workflow(int $id): void
    {
        $this->action($id, 'start-design')->assertOk();
        $this->action($id, 'complete-design')->assertOk();
        $this->action($id, 'send-to-printing', ['printingLocationId' => $this->printer->id])->assertOk();
    }

    public function test_item_details_expose_specifications_without_order_prices_and_require_production_access(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->getJson("/api/v1/production/items/$id/details")->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.orderId', $order->id)
            ->assertJsonPath('data.quantity', 100)
            ->assertJsonPath('data.specifications', 'Glossy double-sided')
            ->assertJsonCount(0, 'data.files')
            ->assertJsonMissingPath('data.agreedTotal')
            ->assertJsonMissingPath('data.unitPrice')
            ->assertJsonMissingPath('data.totalAmount');

        $staff = User::create(['name' => 'Designer', 'username' => 'production_only_test',
            'password' => 'password123', 'status' => UserStatus::ACTIVE]);
        $staff->assignRole($this->staffRole);
        $this->staffRole->givePermissionTo(Permission::findOrCreate('production.items.list', 'web'));
        $this->login($staff);
        $this->getJson("/api/v1/production/items/$id/details")->assertOk()
            ->assertJsonPath('data.specifications', 'Glossy double-sided');
        $this->getJson($this->path($order))->assertForbidden();
        $this->postJson("/api/v1/deliveries/orders/{$order->id}/deliver")->assertForbidden();

        $this->staffRole->revokePermissionTo('production.items.list');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson("/api/v1/production/items/$id/details")->assertForbidden();
    }

    public function test_design_can_finish_in_place_and_requires_printer_on_send(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->getJson('/api/v1/production/items?filter[column]=0')
            ->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->action($id, 'start-design')->assertOk()->assertJsonPath('data', null);
        $this->action($id, 'complete-design')->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $id, 'production_stage' => 2, 'designer_id' => $this->admin->id]);
        $this->getJson('/api/v1/production/items?filter[column]=1')
            ->assertOk()->assertJsonPath('data.items.0.productionStage', 2)
            ->assertJsonPath('data.items.0.designCompletedById', $this->admin->id);
        $this->action($id, 'send-to-printing')->assertUnprocessable()
            ->assertJsonValidationErrors('printingLocationId');
        $this->action($id, 'send-to-printing', ['printingLocationId' => $this->printer->id])
            ->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $id, 'production_stage' => 3,
            'printing_location_id' => $this->printer->id]);
        $this->assertSame(OrderProductionStatus::IN_PRODUCTION, $order->fresh()->production_status);
        $this->assertDatabaseCount('production_item_events', 3);
    }

    public function test_combined_complete_and_send_posts_both_events_atomically(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->action($id, 'start-design')->assertOk();
        $this->action($id, 'complete-and-send-to-printing', ['printingLocationId' => $this->printer->id])->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $id, 'production_stage' => 3]);
        $this->assertDatabaseCount('production_item_events', 3);
        $this->getJson("/api/v1/production/items/$id/history")->assertOk()
            ->assertJsonPath('data.events.0.eventType', 3)
            ->assertJsonPath('data.events.1.eventType', 2);
    }

    public function test_mark_ready_and_undo_moves_back_stepwise_and_preserves_audit(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->workflow($id);
        $this->action($id, 'mark-ready')->assertOk();
        $this->assertSame(OrderProductionStatus::READY_FOR_DELIVERY, $order->fresh()->production_status);
        $this->action($id, 'undo')->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $id, 'production_stage' => 3,
            'printing_location_id' => $this->printer->id]);
        $this->action($id, 'undo')->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $id, 'production_stage' => 1,
            'printing_location_id' => null]);
        $this->action($id, 'undo')->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $id, 'production_stage' => 0,
            'designer_id' => null]);
        $this->action($id, 'undo')->assertStatus(409);
        $this->assertSame(OrderProductionStatus::NOT_STARTED, $order->fresh()->production_status);
        $this->assertDatabaseCount('production_item_events', 7);
    }

    public function test_printing_location_can_change_and_history_keeps_both_locations(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->workflow($id);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/v1/production/items/$id/printing-location",
                ['printingLocationId' => $this->secondPrinter->id])->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $id,
            'printing_location_id' => $this->secondPrinter->id]);
        $this->getJson("/api/v1/production/items/$id/history")->assertOk()
            ->assertJsonPath('data.events.0.oldPrintingLocationName', 'Local Press')
            ->assertJsonPath('data.events.0.newPrintingLocationName', 'Vendor Press');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson("/api/v1/production/items/$id/printing-location",
                ['printingLocationId' => $this->secondPrinter->id])->assertStatus(409);
    }

    public function test_batch_send_all_or_nothing_with_multiple_orders_and_one_location(): void
    {
        $a = $this->confirmed();
        $b = $this->confirmed();
        $idA = $this->item($a);
        $idB = $this->item($b);
        $this->action($idA, 'start-design')->assertOk();
        $this->action($idA, 'complete-design')->assertOk();
        $this->action($idB, 'start-design')->assertOk();
        $base = '/api/v1/production/items/send-to-printing';
        $payload = ['orderItemIds' => [$idA, $idB], 'printingLocationId' => $this->printer->id];
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($base, $payload)->assertStatus(409);
        $this->assertDatabaseHas('order_items', ['id' => $idA, 'production_stage' => 2]);
        $this->action($idB, 'complete-design')->assertOk();
        $this->withHeader('Idempotency-Key', 'batch-two')
            ->postJson($base, $payload)->assertOk();
        $this->postJson($base, $payload)->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $idA, 'production_stage' => 3,
            'printing_location_id' => $this->printer->id]);
        $this->assertDatabaseHas('order_items', ['id' => $idB, 'production_stage' => 3,
            'printing_location_id' => $this->printer->id]);
        $this->assertDatabaseCount('production_item_events', 6);
        $this->postJson($base, ['orderItemIds' => [$idA], 'printingLocationId' => $this->printer->id])
            ->assertStatus(409);
    }

    public function test_batch_rejects_duplicate_ids_and_inactive_location(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $url = '/api/v1/production/items/send-to-printing';
        $this->postJson($url, ['orderItemIds' => [$id, $id],
            'printingLocationId' => $this->printer->id])->assertUnprocessable()
            ->assertJsonValidationErrors('orderItemIds.0');
        $this->printer->status = ParameterValueStatus::INACTIVE;
        $this->printer->save();
        $this->action($id, 'start-design')->assertOk();
        $this->action($id, 'complete-design')->assertOk();
        $this->postJson($url, ['orderItemIds' => [$id],
            'printingLocationId' => $this->printer->id])->assertStatus(409);
        $this->assertDatabaseCount('production_item_events', 2);
    }

    public function test_summary_counts_and_rollup_ready_partially_ready_and_undo(): void
    {
        $order = $this->confirmed(['items' => [
            ['serviceId' => $this->service->id, 'quantity' => 100, 'agreedTotal' => '120.00'],
            ['serviceId' => $this->secondService->id, 'quantity' => 200, 'agreedTotal' => '150.00'],
        ]]);
        [$id1, $id2] = $order->items()->orderBy('id')->pluck('id')->all();
        $this->getJson('/api/v1/production/summary')
            ->assertJsonPath('data.notStartedCount', 2);
        $this->workflow($id1);
        $this->action($id1, 'mark-ready')->assertOk();
        $this->assertSame(OrderProductionStatus::PARTIALLY_READY, $order->fresh()->production_status);
        $this->getJson('/api/v1/production/summary')
            ->assertJsonPath('data.notStartedCount', 1)->assertJsonPath('data.readyCount', 1);
        $this->workflow($id2);
        $this->action($id2, 'mark-ready')->assertOk();
        $this->assertSame(OrderProductionStatus::READY_FOR_DELIVERY, $order->fresh()->production_status);
        $this->action($id1, 'undo')->assertOk();
        $this->assertSame(OrderProductionStatus::PARTIALLY_READY, $order->fresh()->production_status);
    }

    public function test_my_design_filters_use_actor_events_even_after_handover(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->action($id, 'start-design')->assertOk();
        $this->action($id, 'complete-design')->assertOk();
        $this->getJson('/api/v1/production/items?filter[myDesignWork]=1')
            ->assertJsonPath('data.pagination.total', 1);
        $this->getJson('/api/v1/production/items?filter[myDesignWork]=2')
            ->assertJsonPath('data.pagination.total', 1);

        $colleague = User::create(['name' => 'Other Worker', 'username' => 'other_worker',
            'password' => 'password123', 'status' => UserStatus::ACTIVE]);
        $colleague->assignRole($this->adminRole);
        $this->login($colleague);
        $this->getJson('/api/v1/production/items?filter[myDesignWork]=1')
            ->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/v1/production/items?filter[myDesignWork]=2')
            ->assertJsonPath('data.pagination.total', 0);
        $this->action($id, 'send-to-printing', ['printingLocationId' => $this->printer->id])
            ->assertOk();
        $this->getJson('/api/v1/production/items?filter[myDesignWork]=2')
            ->assertJsonPath('data.pagination.total', 0);
        $this->login($this->admin);
        $this->getJson('/api/v1/production/items?filter[myDesignWork]=2')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_draft_and_cancelled_orders_are_excluded_from_board_and_cannot_transition(): void
    {
        $draft = $this->draft();
        $id = $this->item($draft);
        $this->getJson('/api/v1/production/items')->assertJsonPath('data.pagination.total', 0);
        $this->action($id, 'start-design')->assertStatus(409);
        $this->postJson($this->path($draft).'/confirm', [])->assertOk();
        $this->postJson($this->path($draft).'/cancel', ['reason' => 'Cancellation requested'])->assertOk();
        $this->action($id, 'start-design')->assertStatus(409);
        $this->getJson('/api/v1/production/items')->assertJsonPath('data.pagination.total', 0);
    }

    public function test_invalid_transitions_return_business_conflicts_and_missing_ids_are_not_created(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->action($id, 'complete-design')->assertStatus(409);
        $this->action($id, 'send-to-printing', ['printingLocationId' => $this->printer->id])->assertStatus(409);
        $this->action($id, 'mark-ready')->assertStatus(409);
        $this->action(987654, 'start-design')->assertStatus(409);
        $this->assertDatabaseCount('production_item_events', 0);
    }

    public function test_idempotency_same_payload_replays_but_changed_payload_conflicts(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $url = "/api/v1/production/items/$id/start-design";
        $this->withHeader('Idempotency-Key', 'same-action-key')->postJson($url, [])->assertOk();
        $this->postJson($url, [])->assertOk();
        $this->assertDatabaseCount('production_item_events', 1);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($url, [])->assertStatus(409);
    }

    public function test_permissions_block_unauthorized_changes_even_if_user_is_logged_in(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $staff = User::create(['name' => 'Staff', 'username' => 'staff_prod',
            'password' => 'password123', 'status' => UserStatus::ACTIVE]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);
        $this->action($id, 'start-design')->assertForbidden();
        $this->getJson('/api/v1/production/items')->assertForbidden();
    }
    public function test_another_authorized_employee_can_complete_design_and_both_actors_are_audited(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->action($id, 'start-design')->assertOk();

        $staff = User::create(['name' => 'Design Finisher', 'username' => 'design_finisher',
            'password' => 'password123', 'status' => UserStatus::ACTIVE]);
        $staff->assignRole($this->staffRole);
        $this->staffRole->givePermissionTo(Permission::findOrCreate('production.design.complete', 'web'));
        $this->staffRole->givePermissionTo(Permission::findOrCreate('production.items.list', 'web'));
        $this->staffRole->givePermissionTo(Permission::findOrCreate('production.history.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->login($staff);
        $this->action($id, 'complete-design')->assertOk();
        $this->getJson('/api/v1/production/items?filter[myDesignWork]=1')
            ->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/v1/production/items?filter[myDesignWork]=2')
            ->assertJsonPath('data.pagination.total', 1);
        $this->getJson("/api/v1/production/items/$id/history")
            ->assertJsonPath('data.events.0.actorId', $staff->id)
            ->assertJsonPath('data.events.1.actorId', $this->admin->id);
        $this->assertDatabaseHas('order_items', ['id' => $id,
            'production_stage' => 2, 'designer_id' => $this->admin->id]);
    }

    public function test_combined_action_requires_both_permissions(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->action($id, 'start-design')->assertOk();

        $staff = User::create(['name' => 'Completer Only', 'username' => 'completer_only',
            'password' => 'password123', 'status' => UserStatus::ACTIVE]);
        $staff->assignRole($this->staffRole);
        $this->staffRole->givePermissionTo(Permission::findOrCreate('production.design.complete', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->login($staff);

        $this->action($id, 'complete-and-send-to-printing', [
            'printingLocationId' => $this->printer->id,
        ])->assertForbidden();
        $this->action($id, 'complete-design')->assertOk();
        $this->assertDatabaseHas('order_items', ['id' => $id, 'production_stage' => 2]);
    }

    public function test_idempotency_key_reuse_with_different_item_is_rejected(): void
    {
        $order = $this->confirmed(['items' => [
            ['serviceId' => $this->service->id, 'quantity' => 100, 'agreedTotal' => '120.00'],
            ['serviceId' => $this->secondService->id, 'quantity' => 100, 'agreedTotal' => '140.00'],
        ]]);
        [$a, $b] = $order->items()->orderBy('id')->pluck('id')->all();
        $this->withHeader('Idempotency-Key', 'reuse-key')
            ->postJson("/api/v1/production/items/$a/start-design", [])->assertOk();
        $this->postJson("/api/v1/production/items/$b/start-design", [])->assertStatus(409);
        $this->assertDatabaseHas('order_items', ['id' => $a, 'production_stage' => 1]);
        $this->assertDatabaseHas('order_items', ['id' => $b, 'production_stage' => 0]);
    }

    public function test_customer_and_search_filters_limit_board_results(): void
    {
        $first = $this->confirmed();
        $second = $this->confirmed(['customerId' => $this->secondCustomer->id]);
        $this->getJson('/api/v1/production/items?filter[customerId]='.$this->customer->id)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.orderId', $first->id);
        $this->getJson('/api/v1/production/items?filter[search]=Mona')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.orderId', $second->id);
        $this->getJson('/api/v1/production/summary?filter[customerId]='.$this->secondCustomer->id)
            ->assertJsonPath('data.notStartedCount', 1);
    }

    public function test_service_filter_applies_to_board_summary_and_is_composable(): void
    {
        $order = $this->confirmed(['items' => [
            ['serviceId' => $this->service->id, 'quantity' => 100, 'agreedTotal' => '100.00'],
            ['serviceId' => $this->secondService->id, 'quantity' => 100, 'agreedTotal' => '100.00'],
        ]]);
        [$first, $second] = $order->items()->orderBy('id')->pluck('id')->all();
        $path = '/api/v1/production/items?filter[serviceId]='.$this->service->id;
        $this->getJson($path)->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $first)
            ->assertJsonPath('data.items.0.serviceId', $this->service->id);
        $this->getJson($path.'&filter[column]=0&perPage=1')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $first);
        $this->getJson('/api/v1/production/summary?filter[serviceId]='.$this->service->id)
            ->assertOk()
            ->assertJsonPath('data.notStartedCount', 1)
            ->assertJsonPath('data.designInProgressCount', 0);
        $this->action($second, 'start-design')->assertOk();
        $this->getJson('/api/v1/production/summary?filter[serviceId]='.$this->secondService->id)
            ->assertJsonPath('data.notStartedCount', 0)
            ->assertJsonPath('data.designInProgressCount', 1);
        $this->getJson('/api/v1/production/items?filter[serviceId]='.$this->secondService->id.'&filter[column]=1')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $second);
        $this->getJson($path.'&filter[customerId]='.$this->secondCustomer->id)
            ->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/v1/production/summary?filter[serviceId]=999999')
            ->assertJsonPath('data.notStartedCount', 0)
            ->assertJsonPath('data.designInProgressCount', 0);
    }

    public function test_invalid_production_service_filter_is_rejected(): void
    {
        foreach (['0', '-1', 'abc'] as $invalid) {
            $this->getJson('/api/v1/production/items?filter[serviceId]='.$invalid)
                ->assertUnprocessable()->assertJsonValidationErrors('filter.serviceId');
            $this->getJson('/api/v1/production/summary?filter[serviceId]='.$invalid)
                ->assertUnprocessable()->assertJsonValidationErrors('filter.serviceId');
        }
    }

    public function test_successful_idempotent_replay_survives_printer_deactivation(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->action($id, 'start-design')->assertOk();
        $this->action($id, 'complete-design')->assertOk();

        $url = "/api/v1/production/items/$id/send-to-printing";
        $payload = ['printingLocationId' => $this->printer->id];
        $this->withHeader('Idempotency-Key', 'retry-after-disable')
            ->postJson($url, $payload)->assertOk();

        $this->printer->status = ParameterValueStatus::INACTIVE;
        $this->printer->save();
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('production_item_events', 3);
        $this->assertDatabaseHas('order_items', [
            'id' => $id, 'printing_location_id' => $this->printer->id, 'production_stage' => 3,
        ]);
    }

    public function test_location_from_other_parameter_group_is_validation_error(): void
    {
        $order = $this->confirmed();
        $id = $this->item($order);
        $this->action($id, 'start-design')->assertOk();
        $this->action($id, 'complete-design')->assertOk();

        $this->action($id, 'send-to-printing', [
            'printingLocationId' => $this->wholesale->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('printingLocationId');
    }

    public function test_mysql_rollup_uses_a_locking_current_read_for_all_sibling_items(): void
    {
        $order = $this->confirmed(['items' => [
            ['serviceId' => $this->service->id, 'quantity' => 100, 'agreedTotal' => '100.00'],
            ['serviceId' => $this->secondService->id, 'quantity' => 100, 'agreedTotal' => '100.00'],
        ]]);
        $queries = [];
        \Illuminate\Support\Facades\DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->action($this->item($order), 'start-design')->assertOk();
        $this->assertSame(OrderProductionStatus::IN_PRODUCTION, $order->fresh()->production_status);

        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            $this->assertTrue(
                collect($queries)->contains(static fn (string $sql): bool =>
                    str_contains($sql, 'order_items')
                    && str_contains($sql, 'production_stage')
                    && str_contains($sql, 'for update')
                ),
                'Order production status must be rolled up via SELECT ... FOR UPDATE under MySQL REPEATABLE READ.',
            );
        }
    }

}
