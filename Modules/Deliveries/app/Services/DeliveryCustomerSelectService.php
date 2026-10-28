<?php
namespace Modules\Deliveries\Services;

use App\Exceptions\Select\InvalidSelectRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Orders\Enums\OrderItemStage;
use Modules\Orders\Enums\OrderStatus;

class DeliveryCustomerSelectService
{
    /** Only customers visible in the requested delivery queue. */
    public function options(string $scope = 'ready'): array
    {
        if (! in_array($scope, ['ready', 'history'], true)) {
            throw new InvalidSelectRequestException();
        }

        Gate::authorize($scope === 'ready' ? 'deliveries.ready.view' : 'deliveries.list');

        $query = DB::table('orders as orders_for_delivery')
            ->select('orders_for_delivery.customer_id', 'orders_for_delivery.customer_name_snapshot',
                'orders_for_delivery.customer_phone_snapshot')
            ->whereNotNull('orders_for_delivery.customer_id');

        if ($scope === 'ready') {
            $query->where('orders_for_delivery.status', OrderStatus::CONFIRMED->value)
                ->whereNull('orders_for_delivery.delivered_at')
                ->whereExists(function ($items): void {
                    $items->selectRaw('1')->from('order_items')
                        ->whereColumn('order_items.order_id', 'orders_for_delivery.id')
                        ->where('order_items.production_stage', OrderItemStage::READY_FOR_DELIVERY->value);
                });
        } else {
            $query->whereExists(function ($history): void {
                $history->selectRaw('1')->from('deliveries')
                    ->whereColumn('deliveries.order_id', 'orders_for_delivery.id');
            });
        }

        return $query->orderBy('orders_for_delivery.customer_name_snapshot')
            ->orderByDesc('orders_for_delivery.id')
            ->get()
            ->unique('customer_id')
            ->map(static fn ($order): array => [
                'value' => (int) $order->customer_id,
                'label' => $order->customer_name_snapshot.' - '.$order->customer_phone_snapshot,
            ])
            ->values()->all();
    }
}
