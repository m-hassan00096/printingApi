<?php
namespace Modules\Orders\Services;

use Carbon\CarbonImmutable;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;

/**
 * Read-only dashboard based on authoritative Orders data.
 * Metrics count ORDERS, while stage pressure counts ITEMS (never quantity).
 * No Finance / delivery-side private figures are exposed.
 */
class DashboardQueryService
{
    public function overview(): array
    {
        $today = CarbonImmutable::now('Africa/Cairo')->toDateString();
        $open = static fn () => Order::query()
            ->where('status', OrderStatus::CONFIRMED->value)
            ->whereNull('delivered_at');

        $active = $open()->count();
        $late = $open()->whereDate('due_date', '<', $today)->count();
        $ready = $open()->where('production_status', OrderProductionStatus::READY_FOR_DELIVERY->value)->count();
        $dueToday = $open()->whereDate('due_date', '=', $today)->count();
        $readyDueToday = $open()->whereDate('due_date', '=', $today)
            ->where('production_status', OrderProductionStatus::READY_FOR_DELIVERY->value)->count();

        $stageCounts = OrderItem::query()
            ->whereHas('order', static fn ($q) => $q
                ->where('status', OrderStatus::CONFIRMED->value)
                ->whereNull('delivered_at'))
            ->selectRaw('production_stage, COUNT(*) AS item_count')
            ->groupBy('production_stage')
            ->pluck('item_count', 'production_stage');

        $attention = $open()->whereNotNull('due_date')
            ->orderBy('due_date')->orderBy('id')->limit(6)->get();
        $latest = Order::query()->where('status', OrderStatus::CONFIRMED->value)
            ->orderByDesc('id')->limit(5)->get();

        $card = static function (Order $order) use ($today): array {
            $due = $order->due_date?->toDateString();
            $isOverdue = $order->status === OrderStatus::CONFIRMED
                && $order->delivered_at === null
                && $due !== null && $due < $today;
            return [
                'id' => (int) $order->id,
                'orderNumber' => $order->order_number,
                'customerName' => $order->customer_name_snapshot,
                'dueDate' => $due,
                'productionStatus' => $order->production_status?->value,
                'productionStatusLabel' => $order->production_status?->label(),
                'isOverdue' => $isOverdue,
            ];
        };

        return [
            'asOfDate' => $today,
            'metrics' => [
                'activeOrders' => $active,
                'overdueOrders' => $late,
                'readyForDelivery' => $ready,
                'dueToday' => $dueToday,
                'readyDueToday' => $readyDueToday,
            ],
            'productionPressure' => [
                'notStarted' => (int) ($stageCounts[0] ?? 0),
                'design' => (int) ($stageCounts[1] ?? 0) + (int) ($stageCounts[2] ?? 0),
                'printing' => (int) ($stageCounts[3] ?? 0),
                'ready' => (int) ($stageCounts[4] ?? 0),
            ],
            'attentionOrders' => $attention->map($card)->all(),
            'latestOrders' => $latest->map($card)->all(),
        ];
    }
}
