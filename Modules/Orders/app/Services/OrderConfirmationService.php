<?php
namespace Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Modules\Orders\Contracts\ConfirmOrderFinanceData;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;

class OrderConfirmationService
{
    public function __construct(
        private readonly OrderFinanceGateway $finance,
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderActivityWriter $activities,
    ) {}

    public function confirm(Order $order, string $key, ?string $depositAmount, ?int $paymentMethod = null, ?int $paymentDestinationId = null): Order
    {
        return DB::transaction(function () use ($order, $key, $depositAmount, $paymentMethod, $paymentDestinationId): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::DRAFT) {
                throw new OrdersBusinessException('only_drafts_editable');
            }
            if ($locked->items()->count() < 1) {
                throw new OrdersBusinessException('last_item_required');
            }
            $this->finance->recordConfirmation(new ConfirmOrderFinanceData(
                orderId: $locked->id, customerId: $locked->customer_id,
                totalAmount: (string) $locked->total_amount, idempotencyKey: $key,
                depositAmount: $depositAmount, paymentMethod: $paymentMethod,
                paymentDestinationId: $paymentDestinationId, actorId: Auth::id(),
            ));

            $locked->order_number = $this->numbers->next();
            $locked->status = OrderStatus::CONFIRMED;
            $locked->production_status = OrderProductionStatus::NOT_STARTED;
            $locked->confirmed_at = now();
            $locked->save();

            $this->activities->record($locked, 'order_confirmed', [
                'orderNumber' => $locked->order_number,
                'totalAmount' => (string) $locked->total_amount,
            ]);
            return $locked->fresh()->load(['customer', 'items.files']);
        }, 3);
    }
}
