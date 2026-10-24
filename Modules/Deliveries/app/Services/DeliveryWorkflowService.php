<?php
namespace Modules\Deliveries\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Deliveries\Contracts\DeliveryFinanceGateway;
use Modules\Deliveries\Contracts\DeliveryOrderGateway;
use Modules\Deliveries\Enums\DeliveryStatus;
use Modules\Deliveries\Exceptions\DeliveryException;
use Modules\Deliveries\Models\Delivery;

class DeliveryWorkflowService
{
    public function __construct(
        private readonly DeliveryOrderGateway $orders,
        private readonly DeliveryFinanceGateway $finance,
        private readonly DeliveryIdempotencyService $keys,
    ) {}

    public function deliver(int $orderId, array $data, int $actorId, string $actorName, string $key): void
    {
        $payload = ['orderId' => $orderId, 'receiverName' => $data['receiverName'] ?? null,
            'collectedAmount' => $data['collectedAmount'] ?? '0.00',
            'paymentMethod' => $data['paymentMethod'] ?? null,
            'paymentDestinationId' => $data['paymentDestinationId'] ?? null,
            'confirmCreditBalance' => $data['confirmCreditBalance'] ?? false];

        DB::transaction(function () use ($orderId, $payload, $actorId, $actorName, $key): void {
            $claim = $this->keys->claim($actorId, 'deliver', $key, $payload);
            if ($claim->result_delivery_id !== null) return;
            $order = $this->orders->lockEligible($orderId);
            if (Delivery::query()->where('order_id', $orderId)
                ->where('status', DeliveryStatus::ACTIVE->value)->exists()) {
                throw new DeliveryException('already_delivered');
            }
            $collectionId = null;
            if (\Modules\Deliveries\Support\DeliveryAmount::cents($payload['collectedAmount']) > 0) {
                $collectionId = $this->finance->post($orderId, $order['customerId'],
                    $payload['collectedAmount'], (int) $payload['paymentMethod'],
                    $payload['paymentDestinationId'], $actorId, (bool) $payload['confirmCreditBalance']);
            }
            // Store the real instant in UTC (Laravel/database timezone); derive the Cairo date separately.
            $time = CarbonImmutable::now('UTC');
            $localDate = $time->setTimezone('Africa/Cairo')->toDateString();
            $delivery = Delivery::query()->create([
                'order_id' => $orderId, 'collection_id' => $collectionId,
                'receiver_name' => $payload['receiverName'], 'delivered_by' => $actorId,
                'delivered_by_name_snapshot' => $actorName, 'delivered_at' => $time,
                'delivered_local_date' => $localDate, 'status' => DeliveryStatus::ACTIVE->value,
            ]);
            $this->orders->markDelivered($orderId, $time, $localDate);
            $this->keys->finish($claim, $delivery->id);
        }, 3);
    }

    public function cancel(int $deliveryId, array $data, int $actorId, string $actorName, string $key): void
    {
        $payload = ['deliveryId' => $deliveryId, 'reason' => $data['reason'],
            'fundsReturnConfirmed' => $data['fundsReturnConfirmed'] ?? false];
        DB::transaction(function () use ($deliveryId, $payload, $actorId, $actorName, $key): void {
            $claim = $this->keys->claim($actorId, 'cancel', $key, $payload);
            if ($claim->result_delivery_id !== null) return;

            // Initial FK lookup does not lock, next lock is always on the Orders owner.
            $reference = Delivery::query()->findOrFail($deliveryId);
            $order = $this->orders->lockDelivered($reference->order_id);
            $delivery = Delivery::query()->whereKey($deliveryId)->lockForUpdate()->firstOrFail();
            if ($delivery->status !== DeliveryStatus::ACTIVE || $delivery->order_id !== $order['orderId']) {
                throw new DeliveryException('already_cancelled');
            }
            if ($delivery->collection_id !== null) {
                $this->finance->reverse($delivery->collection_id, $order['orderId'],
                    $order['customerId'], $actorId, $payload['reason'], $payload['fundsReturnConfirmed']);
            }
            $delivery->status = DeliveryStatus::CANCELLED;
            $delivery->cancelled_by = $actorId;
            $delivery->cancelled_by_name_snapshot = $actorName;
            $delivery->cancelled_at = now();
            $delivery->cancellation_reason = $payload['reason'];
            $delivery->save();

            $this->orders->restoreReady($order['orderId']);
            $this->keys->finish($claim, $deliveryId);
        }, 3);
    }
}
