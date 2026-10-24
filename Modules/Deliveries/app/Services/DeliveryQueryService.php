<?php
namespace Modules\Deliveries\Services;

use Illuminate\Support\Facades\DB;
use Modules\Deliveries\Contracts\DeliveryFinanceGateway;
use Modules\Deliveries\Contracts\DeliveryOrderGateway;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Models\Delivery;

class DeliveryQueryService
{
    public function __construct(private readonly DeliveryOrderGateway $orders,
        private readonly DeliveryFinanceGateway $finance) {}

    public function ready(array $filters, int $page, int $perPage, string $sort, bool $mayDeliver, bool $mayBalance): array
    {
        $result = $this->orders->ready($filters, $page, $perPage, $sort);
        $balances = $mayBalance ? $this->finance->balances(array_column($result['orders'], 'customerId')) : [];
        foreach ($result['orders'] as &$order) {
            $order['canDeliver'] = $mayDeliver && $order['isReadyForDelivery'];
            $order['customerBalance'] = $mayBalance ? ($balances[$order['customerId']] ?? null) : null;
        }
        unset($order);
        return $result;
    }

    public function preview(int $orderId, bool $mayDeliver, bool $mayCollect, bool $mayBalance): array
    {
        $order = $this->orders->preview($orderId);
        abort_if($order === null, 404);
        $balance = $mayBalance ? $this->finance->balances([$order['customerId']]) : [];
        return [
            'order' => $order,
            'customerBalance' => $mayBalance ? ($balance[$order['customerId']] ?? null) : null,
            'canDeliver' => $mayDeliver && $order['isReadyForDelivery'],
            'canCollect' => $mayDeliver && $mayCollect && $order['isReadyForDelivery'],
        ];
    }

    public function history(array $filters, int $page, int $perPage, string $sort, bool $mayFinance, bool $mayCancel): array
    {
        $q = Delivery::query();
        if (isset($filters['status'])) $q->where('status', $filters['status']);
        if (isset($filters['dateFrom'])) $q->where('delivered_local_date', '>=', $filters['dateFrom']);
        if (isset($filters['dateTo'])) $q->where('delivered_local_date', '<=', $filters['dateTo']);
        if (! empty($filters['search'])) {
            $s = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']);
            $like = '%'.$s.'%';
            $q->whereIn('order_id', function ($sub) use ($like): void {
                $sub->from('orders')->select('id')->where(function ($or) use ($like): void {
                    $or->whereRaw("order_number LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("customer_name_snapshot LIKE ? ESCAPE '!'", [$like])
                        ->orWhereRaw("customer_phone_snapshot LIKE ? ESCAPE '!'", [$like]);
                });
            });
        }
        if (in_array($sort, ['deliveredAt', '-deliveredAt'], true)) {
            $q->orderBy('delivered_at', $sort === 'deliveredAt' ? 'asc' : 'desc')
                ->orderBy('id', $sort === 'deliveredAt' ? 'asc' : 'desc');
        } else $q->orderBy('id', $sort === 'id' ? 'asc' : 'desc');

        $p = $q->paginate($perPage, ['*'], 'page', $page);
        $deliveries = $p->getCollection();
        $orders = $this->orders->snapshots($deliveries->pluck('order_id')->all());
        $cols = $this->finance->collections($deliveries->pluck('collection_id')->filter()->all());
        return [
            'deliveries' => $deliveries->map(fn (Delivery $d) =>
                $this->record($d, $orders[$d->order_id] ?? [], $cols[$d->collection_id] ?? null, $mayFinance, $mayCancel, false))->all(),
            'pagination' => ['currentPage' => $p->currentPage(), 'lastPage' => $p->lastPage(),
                'perPage' => $p->perPage(), 'total' => $p->total()],
        ];
    }

    public function show(int $id, bool $mayFinance, bool $mayCancel): array
    {
        $d = Delivery::query()->findOrFail($id);
        $orders = $this->orders->snapshots([$d->order_id]);
        $cols = $this->finance->collections($d->collection_id === null ? [] : [$d->collection_id]);
        return ['delivery' => $this->record($d, $orders[$d->order_id] ?? [],
            $cols[$d->collection_id] ?? null, $mayFinance, $mayCancel, true)];
    }

    private function record(Delivery $d, array $order, ?array $c, bool $mayFinance, bool $mayCancel, bool $details): array
    {
        $received = $c['amount'] ?? '0.00';
        $row = [
            'id' => $d->id, 'orderId' => $d->order_id,
            'orderNumber' => $order['orderNumber'] ?? null, 'customerId' => $order['customerId'] ?? null,
            'customerName' => $order['customerName'] ?? null, 'customerPhone' => $order['customerPhone'] ?? null,
            'deliveredById' => $d->delivered_by, 'deliveredByName' => $d->delivered_by_name_snapshot,
            'receiverName' => $d->receiver_name, 'receivedAmount' => $received,
            'effectiveReceivedAmount' => $c === null || $c['status'] === 2 ? '0.00' : $received,
            'collectionId' => $mayFinance ? ($c['id'] ?? null) : null,
            'receiptNumber' => $mayFinance ? ($c['receiptNumber'] ?? null) : null,
            'collectionStatus' => $c['status'] ?? null,
            'status' => $d->status->value, 'statusLabel' => $d->status->label(),
            'deliveredAt' => $d->delivered_at?->timezone('Africa/Cairo')->toIso8601String(),
            'deliveredLocalDate' => $d->delivered_local_date?->format('Y-m-d'),
            'cancelledAt' => $d->cancelled_at?->timezone('Africa/Cairo')->toIso8601String(),
            'cancelledByName' => $d->cancelled_by_name_snapshot, 'cancellationReason' => $d->cancellation_reason,
        ];
        if ($details) {
            $row['orderTotal'] = $order['orderTotal'] ?? null;
            $row['collection'] = $mayFinance ? $c : null;
            $row['cancelledById'] = $d->cancelled_by;
            $row['canCancel'] = $mayCancel && $d->status === DeliveryStatus::ACTIVE;
        }
        return $row;
    }
}
