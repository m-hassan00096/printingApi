<?php
namespace Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Models\Order;

/**
 * The request may choose DRAFT or CONFIRMED. Never commit a partial Draft
 * when direct confirmation cannot post the required financial movements.
 */
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
            // Fingerprint the complete payload (including requested status
            // and deposit), making a retried direct-confirm request safe.
            $order = $this->draftCreation->create($data, $idempotencyKey);

            if ((int) ($data['status'] ?? OrderStatus::DRAFT->value) === OrderStatus::CONFIRMED->value
                && $order->status === OrderStatus::DRAFT) {
                $order = $this->confirmation->confirm(
                    $order, $idempotencyKey, $data['depositAmount'] ?? null,
                );
            }

            return $order;
        }, 3);
    }

    public function update(Order $order, array $data, ?string $idempotencyKey = null): Order
    {
        return DB::transaction(function () use ($order, $data, $idempotencyKey): Order {
            $updated = $this->drafts->update($order, $data);

            if ((int) ($data['status'] ?? OrderStatus::DRAFT->value) === OrderStatus::CONFIRMED->value) {
                $updated = $this->confirmation->confirm(
                    $updated, $idempotencyKey ?? '', $data['depositAmount'] ?? null,
                );
            }

            return $updated;
        }, 3);
    }
}
