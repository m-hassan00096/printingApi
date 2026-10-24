<?php
namespace Modules\Orders\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Deliveries\Contracts\DeliveryOrderGateway;
use Modules\Deliveries\Exceptions\DeliveryException;
use Modules\Orders\Enums\OrderItemStage;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

class OrdersDeliveryAdapter implements DeliveryOrderGateway
{
    public function __construct(private readonly OrderActivityWriter $activities) {}

    public function ready(array $filters, int $page, int $perPage, string $sort): array
    {
        $query = Order::query()->where('status', OrderStatus::CONFIRMED->value)
            ->whereNull('delivered_at')
            ->whereHas('items', fn (Builder $q) => $q->where('production_stage', OrderItemStage::READY_FOR_DELIVERY->value))
            ->with('items')
            ->withCount(['items', 'items as ready_items_count' =>
                fn (Builder $q) => $q->where('production_stage', OrderItemStage::READY_FOR_DELIVERY->value)]);
        if (isset($filters['customerId'])) $query->where('customer_id', $filters['customerId']);
        if (! empty($filters['search'])) {
            $s = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']);
            $like = '%'.$s.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereRaw("order_number LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("customer_name_snapshot LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("customer_phone_snapshot LIKE ? ESCAPE '!'", [$like]);
            });
        }
        if (in_array($sort, ['dueDate', '-dueDate'], true)) {
            $query->orderByRaw('due_date IS NULL ASC')
                ->orderBy('due_date', $sort === 'dueDate' ? 'asc' : 'desc')->orderBy('id');
        } else {
            $query->orderBy('id', $sort === '-id' ? 'desc' : 'asc');
        }
        $p = $query->paginate($perPage, ['*'], 'page', $page);
        return [
            'orders' => $p->getCollection()->map(fn (Order $order) => $this->card($order))->all(),
            'pagination' => ['currentPage' => $p->currentPage(), 'lastPage' => $p->lastPage(),
                'perPage' => $p->perPage(), 'total' => $p->total()],
        ];
    }

    public function preview(int $orderId): ?array
    {
        $order = Order::query()->whereKey($orderId)
            ->where('status', OrderStatus::CONFIRMED->value)->whereNull('delivered_at')
            ->whereHas('items', fn (Builder $q) => $q->where('production_stage', 4))
            ->with('items')->withCount(['items', 'items as ready_items_count' =>
                fn (Builder $q) => $q->where('production_stage', 4)])->first();
        return $order === null ? null : $this->card($order);
    }

    private function card(Order $o): array
    {
        $count = (int) $o->items_count;
        $ready = (int) $o->ready_items_count;
        return [
            'orderId' => $o->id, 'orderNumber' => $o->order_number,
            'customerId' => $o->customer_id, 'customerName' => $o->customer_name_snapshot,
            'customerPhone' => $o->customer_phone_snapshot,
            'dueDate' => $o->due_date?->format('Y-m-d'), 'orderTotal' => (string) $o->total_amount,
            'productionStatus' => $o->production_status->value,
            'productionStatusLabel' => $o->production_status->label(),
            'readyItemsCount' => $ready, 'totalItemsCount' => $count,
            'isReadyForDelivery' => $count > 0 && $ready === $count,
            'items' => $o->items->map(fn ($i) => [
                'id' => $i->id, 'serviceName' => $i->service_name_snapshot,
                'quantity' => (int) $i->quantity,
                'productionStage' => $i->production_stage->value,
                'productionStageLabel' => __('production.stages.'.$i->production_stage->value),
            ])->all(),
        ];
    }

    public function lockEligible(int $orderId): array
    {
        $o = Order::query()->whereKey($orderId)->lockForUpdate()->first();
        if ($o === null) throw new DeliveryException('order_unavailable');
        if ($o->status !== OrderStatus::CONFIRMED || $o->delivered_at !== null
            || $o->production_status !== OrderProductionStatus::READY_FOR_DELIVERY) {
            throw new DeliveryException('order_unavailable');
        }
        // Locking current-read: no stale sibling item snapshot under MySQL REPEATABLE READ.
        $items = $o->items()->orderBy('id')->lockForUpdate()->get();
        if ($items->isEmpty() || $items->contains(
            fn ($i) => $i->production_stage !== OrderItemStage::READY_FOR_DELIVERY)) {
            throw new DeliveryException('not_fully_ready');
        }
        return ['orderId' => $o->id, 'customerId' => $o->customer_id];
    }

    public function markDelivered(int $orderId, \DateTimeInterface $instant, string $localDate): void
    {
        $o = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        if ($o->delivered_at !== null || $o->production_status !== OrderProductionStatus::READY_FOR_DELIVERY)
            throw new DeliveryException('order_unavailable');
        $o->delivered_at = $instant;
        $o->delivered_local_date = $localDate;
        $o->production_status = OrderProductionStatus::DELIVERED;
        $o->save();
        $this->activities->record($o, 'order_delivered');
    }

    public function lockDelivered(int $orderId): array
    {
        $o = Order::query()->whereKey($orderId)->lockForUpdate()->first();
        if ($o === null || $o->status !== OrderStatus::CONFIRMED
            || $o->delivered_at === null || $o->production_status !== OrderProductionStatus::DELIVERED) {
            throw new DeliveryException('order_not_delivered');
        }
        return ['orderId' => $o->id, 'customerId' => $o->customer_id];
    }

    public function restoreReady(int $orderId): void
    {
        $o = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        if ($o->delivered_at === null || $o->production_status !== OrderProductionStatus::DELIVERED)
            throw new DeliveryException('order_not_delivered');
        $items = $o->items()->orderBy('id')->lockForUpdate()->get();
        if ($items->isEmpty() || $items->contains(fn ($i) => $i->production_stage !== OrderItemStage::READY_FOR_DELIVERY))
            throw new DeliveryException('not_fully_ready');
        $o->delivered_at = null;
        $o->delivered_local_date = null;
        $o->production_status = OrderProductionStatus::READY_FOR_DELIVERY;
        $o->save();
        $this->activities->record($o, 'order_delivery_cancelled');
    }

    public function snapshots(array $orderIds): array
    {
        if ($orderIds === []) return [];
        return Order::withTrashed()->whereIn('id', $orderIds)->get()
            ->mapWithKeys(fn (Order $o) => [$o->id => [
                'orderId' => $o->id, 'orderNumber' => $o->order_number, 'customerId' => $o->customer_id,
                'customerName' => $o->customer_name_snapshot, 'customerPhone' => $o->customer_phone_snapshot,
                'orderTotal' => (string) $o->total_amount,
            ]])->all();
    }
}
