<?php
namespace Modules\Orders\Tests\Feature;

require_once __DIR__.'/Support/OrdersTestCase.php';

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\User;
use Modules\Orders\Enums\OrderItemStage;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Tests\Feature\Support\OrdersTestCase;

class DashboardApiTest extends OrdersTestCase
{
    private function confirmed(string $due, OrderProductionStatus $production, OrderItemStage $stage)
    {
        $order = $this->draft(['dueDate' => now('Africa/Cairo')->addDays(5)->toDateString()]);
        $order->due_date = $due;
        $order->status = OrderStatus::CONFIRMED;
        $order->production_status = $production;
        $order->confirmed_at = now();
        $order->save();
        $item = $order->items()->firstOrFail();
        $item->production_stage = $stage;
        $item->save();
        return $order;
    }

    public function test_real_order_metrics_and_item_stage_pressure_exclude_drafts_and_delivered(): void
    {
        CarbonImmutable::setTestNow('2026-10-26 12:00:00');
        try {
            $late = $this->confirmed('2026-10-24', OrderProductionStatus::IN_PRODUCTION, OrderItemStage::PRINTING);
            $today = $this->confirmed('2026-10-26', OrderProductionStatus::READY_FOR_DELIVERY, OrderItemStage::READY_FOR_DELIVERY);
            $this->confirmed('2026-10-28', OrderProductionStatus::IN_PRODUCTION, OrderItemStage::DESIGN_IN_PROGRESS);
            $this->draft(['dueDate' => '2026-11-20']);

            $delivered = $this->confirmed('2026-10-20', OrderProductionStatus::DELIVERED, OrderItemStage::READY_FOR_DELIVERY);
            $delivered->delivered_at = now();
            $delivered->save();

            $this->getJson('/api/v1/dashboard')->assertOk()
                ->assertJsonPath('data.asOfDate', '2026-10-26')
                ->assertJsonPath('data.metrics.activeOrders', 3)
                ->assertJsonPath('data.metrics.overdueOrders', 1)
                ->assertJsonPath('data.metrics.readyForDelivery', 1)
                ->assertJsonPath('data.metrics.dueToday', 1)
                ->assertJsonPath('data.productionPressure.printing', 1)
                ->assertJsonPath('data.productionPressure.design', 1)
                ->assertJsonPath('data.productionPressure.ready', 1)
                ->assertJsonPath('data.productionPressure.notStarted', 0)
                ->assertJsonPath('data.attentionOrders.0.id', $late->id)
                ->assertJsonPath('data.attentionOrders.0.isOverdue', true)
                ->assertJsonPath('data.attentionOrders.1.id', $today->id)
                ->assertJsonPath('data.attentionOrders.1.isOverdue', false)
                ->assertJsonCount(3, 'data.attentionOrders')
                ->assertJsonCount(4, 'data.latestOrders');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_dashboard_is_protected_by_order_list_permission(): void
    {
        $this->flushSession();
        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();

        $staff = User::create(['name' => 'Staff', 'username' => 'dashboard_staff',
            'password' => 'password123', 'status' => UserStatus::ACTIVE]);
        $staff->assignRole($this->staffRole);
        $this->login($staff);
        $this->getJson('/api/v1/dashboard')->assertForbidden();
        $this->staffRole->givePermissionTo(Permission::findOrCreate('orders.list', 'web'));
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.metrics.activeOrders', 0)
            ->assertJsonPath('data.productionPressure.printing', 0);
    }
}
