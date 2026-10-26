<?php
namespace Modules\Orders\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Orders\Enums\OrderItemStage;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Production\Contracts\ProductionOrderGateway;
use Modules\Production\Exceptions\ProductionException;

class OrdersProductionAdapter implements ProductionOrderGateway
{
    public function transition(array $itemIds, string $action, int $actorId, ?int $printingLocationId): array
    {
        sort($itemIds, SORT_NUMERIC);
        // Read IDs without locking, then lock *orders first* (same lock order as cancellation),
        // then all affected items. Recheck the authoritative state after acquiring locks.
        $orderIds = OrderItem::query()->whereIn('id', $itemIds)->distinct()->pluck('order_id')->all();
        sort($orderIds, SORT_NUMERIC);
        if (count($orderIds) === 0) {
            throw new ProductionException('item_unavailable');
        }

        $orders = Order::query()->whereIn('id', $orderIds)->orderBy('id')
            ->lockForUpdate()->get()->keyBy('id');
        if ($orders->count() !== count($orderIds)) {
            throw new ProductionException('item_unavailable');
        }
        foreach ($orders as $order) {
            if ($order->status !== OrderStatus::CONFIRMED || $order->delivered_at !== null
                || $order->production_status === OrderProductionStatus::DELIVERED) {
                throw new ProductionException('order_unavailable');
            }
        }

        $items = OrderItem::query()->whereIn('id', $itemIds)->orderBy('id')
            ->lockForUpdate()->get();
        if ($items->count() !== count($itemIds)) {
            throw new ProductionException('item_unavailable');
        }

        $events = [];
        foreach ($items as $item) {
            $stage = $item->production_stage;
            $old = $item->printing_location_id === null ? null : (int) $item->printing_location_id;
            $push = function (string $event, int $from, int $to, ?int $oldId, ?int $newId) use (&$events, $item): void {
                $events[] = ['orderItemId' => $item->id, 'event' => $event, 'fromStage' => $from,
                    'toStage' => $to, 'oldLocationId' => $oldId, 'newLocationId' => $newId];
            };
            switch ($action) {
                case 'start_design':
                    $this->requireStage($stage, OrderItemStage::NOT_STARTED);
                    $item->production_stage = OrderItemStage::DESIGN_IN_PROGRESS;
                    $item->designer_id = $actorId;
                    $push('design_started', 0, 1, $old, null);
                    break;
                case 'complete_design':
                    $this->requireStage($stage, OrderItemStage::DESIGN_IN_PROGRESS);
                    $item->production_stage = OrderItemStage::DESIGN_COMPLETED;
                    $push('design_completed', 1, 2, $old, $old);
                    break;
                case 'complete_and_send':
                    $this->requireStage($stage, OrderItemStage::DESIGN_IN_PROGRESS);
                    $this->requireLocation($printingLocationId);
                    $item->production_stage = OrderItemStage::PRINTING;
                    $item->printing_location_id = $printingLocationId;
                    $push('design_completed', 1, 2, $old, $old);
                    $push('sent_to_printing', 2, 3, $old, $printingLocationId);
                    break;
                case 'send_to_printing':
                    $this->requireStage($stage, OrderItemStage::DESIGN_COMPLETED);
                    $this->requireLocation($printingLocationId);
                    $item->production_stage = OrderItemStage::PRINTING;
                    $item->printing_location_id = $printingLocationId;
                    $push('sent_to_printing', 2, 3, $old, $printingLocationId);
                    break;
                case 'change_location':
                    $this->requireStage($stage, OrderItemStage::PRINTING);
                    $this->requireLocation($printingLocationId);
                    if ($old === $printingLocationId) {
                        throw new ProductionException('location_unchanged');
                    }
                    $item->printing_location_id = $printingLocationId;
                    $push('printing_location_changed', 3, 3, $old, $printingLocationId);
                    break;
                case 'mark_ready':
                    $this->requireStage($stage, OrderItemStage::PRINTING);
                    if ($old === null) {
                        throw new ProductionException('location_required');
                    }
                    $item->production_stage = OrderItemStage::READY_FOR_DELIVERY;
                    $push('printing_completed', 3, 4, $old, $old);
                    break;
                case 'undo':
                    [$to, $clearDesigner, $clearLocation] = match ($stage) {
                        OrderItemStage::DESIGN_IN_PROGRESS => [0, true, false],
                        OrderItemStage::DESIGN_COMPLETED => [1, false, false],
                        OrderItemStage::PRINTING => [1, false, true],
                        OrderItemStage::READY_FOR_DELIVERY => [3, false, false],
                        default => throw new ProductionException('cannot_undo'),
                    };
                    $item->production_stage = OrderItemStage::from($to);
                    if ($clearDesigner) {
                        $item->designer_id = null;
                    }
                    if ($clearLocation) {
                        $item->printing_location_id = null;
                    }
                    $push('undone', $stage->value, $to, $old,
                        $item->printing_location_id === null ? null : (int) $item->printing_location_id);
                    break;
                default:
                    throw new ProductionException('invalid_action');
            }
            $item->save();
        }

        foreach ($orders as $order) {
            // MySQL REPEATABLE READ: the earlier plain item->order_id lookup may have
            // opened a stale consistent-read snapshot BEFORE waiting on this order's lock.
            // A locking read below always sees the latest committed sibling item stages.
            // Lock all siblings in PK order while holding the order lock.
            $statuses = $order->items()->orderBy('id')->lockForUpdate()
                ->pluck('production_stage')->map(
                fn ($s): int => $s instanceof OrderItemStage ? $s->value : (int) $s,
            );
            if ($statuses->isEmpty()) {
                throw new ProductionException('item_unavailable');
            }
            $ready = $statuses->filter(fn (int $s) => $s === OrderItemStage::READY_FOR_DELIVERY->value)->count();
            $order->production_status = match (true) {
                $statuses->every(fn (int $s) => $s === 0) => OrderProductionStatus::NOT_STARTED,
                $ready === $statuses->count() => OrderProductionStatus::READY_FOR_DELIVERY,
                $ready > 0 => OrderProductionStatus::PARTIALLY_READY,
                default => OrderProductionStatus::IN_PRODUCTION,
            };
            $order->save();
        }
        return $events;
    }

    public function listItems(array $filters, int $page, int $perPage, string $sort, ?Builder $myItemQuery): array
    {
        $query = $this->filtered($filters, $myItemQuery)->with(['order', 'designer']);
        $column = (int) ($filters['column'] ?? -1);
        if ($column === 0) {
            $query->where('production_stage', 0);
        } elseif ($column === 1) {
            $query->whereIn('production_stage', [1, 2]);
        } elseif ($column === 2) {
            $query->where('production_stage', 3);
        } elseif ($column === 3) {
            $query->where('production_stage', 4);
        }
        $columnMap = [
            'id' => ['id', 'asc'], '-id' => ['id', 'desc'],
            'dueDate' => ['due_date', 'asc'], '-dueDate' => ['due_date', 'desc'],
        ];
        [$key, $direction] = $columnMap[$sort] ?? ['due_date', 'asc'];
        if ($key === 'due_date') {
            $query->join('orders as production_sort_orders', 'production_sort_orders.id', '=', 'order_items.order_id')
                ->select('order_items.*')
                ->orderByRaw('production_sort_orders.due_date IS NULL ASC')
                ->orderBy('production_sort_orders.due_date', $direction);
        } else {
            $query->orderBy('order_items.id', $direction);
        }
        $result = $query->orderBy('order_items.id', 'asc')->paginate($perPage, ['*'], 'page', $page);
        $today = CarbonImmutable::now('Africa/Cairo')->toDateString();
        return [
            'items' => $result->getCollection()->map(function (OrderItem $item) use ($today): array {
                $order = $item->order;
                return [
                    'id' => $item->id, 'orderId' => $order->id,
                    'orderNumber' => $order->order_number,
                    'customerId' => $order->customer_id,
                    'customerName' => $order->customer_name_snapshot,
                    'customerPhone' => $order->customer_phone_snapshot,
                    'serviceId' => $item->printing_service_id,
                    'serviceName' => $item->service_name_snapshot,
                    'quantity' => $item->quantity,
                    'dueDate' => $order->due_date?->format('Y-m-d'),
                    'isLate' => $order->due_date !== null && $order->due_date->format('Y-m-d') < $today,
                    'productionStage' => $item->production_stage->value,
                    'designerId' => $item->designer_id,
                    'designerName' => $item->designer?->name,
                    'printingLocationId' => $item->printing_location_id,
                ];
            })->all(),
            'pagination' => ['currentPage' => $result->currentPage(),
                'lastPage' => $result->lastPage(), 'perPage' => $result->perPage(),
                'total' => $result->total()],
        ];
    }

    public function summary(array $filters, ?Builder $myItemQuery): array
    {
        $query = $this->filtered($filters, $myItemQuery);
        $counts = $query->reorder()->selectRaw('order_items.production_stage, COUNT(*) AS item_count')
            ->groupBy('order_items.production_stage')->pluck('item_count', 'production_stage');
        $today = CarbonImmutable::now('Africa/Cairo')->toDateString();
        $lateOrders = $this->filtered($filters, $myItemQuery)
            ->whereHas('order', fn (Builder $q) => $q->whereDate('due_date', '<', $today))
            ->distinct()->count('order_items.order_id');
        return [
            'notStartedCount' => (int) ($counts[0] ?? 0),
            'designInProgressCount' => (int) ($counts[1] ?? 0),
            'designCompletedCount' => (int) ($counts[2] ?? 0),
            'printingCount' => (int) ($counts[3] ?? 0),
            'readyCount' => (int) ($counts[4] ?? 0),
            'lateOrdersCount' => $lateOrders,
        ];
    }

    public function itemExists(int $itemId): bool
    {
        return OrderItem::query()->whereKey($itemId)->whereHas('order',
            fn (Builder $q) => $q->where('status', '!=', OrderStatus::DRAFT->value))->exists();
    }

    private function filtered(array $filters, ?Builder $myItemQuery): Builder
    {
        $today = CarbonImmutable::now('Africa/Cairo')->toDateString();
        $query = OrderItem::query()->whereHas('order',
            fn (Builder $q) => $q->where('status', OrderStatus::CONFIRMED->value)->whereNull('delivered_at'));
        if (isset($filters['customerId'])) {
            $query->whereHas('order', fn (Builder $q) => $q->where('customer_id', $filters['customerId']));
        }
        if (isset($filters['serviceId'])) {
            // Order items retain the exact printing service ID even if that
            // service is subsequently disabled or soft-deleted.
            $query->where('order_items.printing_service_id', (int) $filters['serviceId']);
        }
        if (! empty($filters['lateOnly'])) {
            $query->whereHas('order', fn (Builder $q) => $q->whereDate('due_date', '<', $today));
        }
        if ($myItemQuery !== null) {
            // Keep historical work filtering inside SQL; never load unbounded item IDs into PHP.
            $query->whereIn('order_items.id', $myItemQuery);
        }
        if (! empty($filters['search'])) {
            $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $filters['search']);
            $like = '%'.$search.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereRaw("order_items.service_name_snapshot LIKE ? ESCAPE '!'", [$like])
                    ->orWhereHas('order', function (Builder $orders) use ($like): void {
                        $orders->whereRaw("order_number LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("customer_name_snapshot LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("customer_phone_snapshot LIKE ? ESCAPE '!'", [$like]);
                    });
            });
        }
        return $query;
    }

    private function requireStage(OrderItemStage $actual, OrderItemStage $expected): void
    {
        if ($actual !== $expected) {
            throw new ProductionException('invalid_stage');
        }
    }

    private function requireLocation(?int $id): void
    {
        if ($id === null) {
            throw new ProductionException('location_required');
        }
    }
}
