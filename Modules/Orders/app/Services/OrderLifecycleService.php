<?php
namespace Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

class OrderLifecycleService
{
    public function __construct(
        private readonly OrderDraftIdempotencyService $draftCreation,
        private readonly DraftOrderService $drafts,
        private readonly OrderConfirmationService $confirmation,
    ) {}

    public function create(array $data, string $idempotencyKey): Order
    {
        return DB::transaction(function () use ($data, $idempotencyKey): Order {
            $order = $this->draftCreation->create($data, $idempotencyKey);
            if ((int) ($data['status'] ?? OrderStatus::DRAFT->value) === OrderStatus::CONFIRMED->value
                && $order->status === OrderStatus::DRAFT) {
                $order = $this->confirmation->confirm($order, $idempotencyKey,
                    $data['depositAmount'] ?? null, $data['paymentMethod'] ?? null,
                    $data['paymentDestinationId'] ?? null);
            }
            return $order;
        }, 3);
    }

    public function update(Order $order, array $data, ?string $idempotencyKey = null): Order
    {
        return DB::transaction(function () use ($order, $data, $idempotencyKey): Order {
            $updated = $this->drafts->update($order, $data);
            if ((int) ($data['status'] ?? OrderStatus::DRAFT->value) === OrderStatus::CONFIRMED->value) {
                $updated = $this->confirmation->confirm($updated, $idempotencyKey ?? '',
                    $data['depositAmount'] ?? null, $data['paymentMethod'] ?? null,
                    $data['paymentDestinationId'] ?? null);
            }
            return $updated;
        }, 3);
    }
}
