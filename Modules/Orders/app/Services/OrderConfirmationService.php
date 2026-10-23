<?php
namespace Modules\Orders\Services;

use Illuminate\Support\Facades\DB;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;

/**
 * Shared confirmation path used by POST /orders (direct confirmation),
 * PATCH /orders/{order} (Draft -> Confirmed), and /orders/{order}/confirm.
 *
 * The Finance gateway must post its ledger in the same DB transaction.
 */
class OrderConfirmationService
{
    public function __construct(
        private readonly OrderFinanceGateway $finance,
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderActivityWriter $activities,
    ) {}

    public function confirm(Order $order, string $key, ?string $depositAmount): Order
    {
        return DB::transaction(function () use ($order, $key, $depositAmount): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::DRAFT) {
                throw new OrdersBusinessException('only_drafts_editable');
            }

            if ($locked->items()->count() < 1) {
                throw new OrdersBusinessException('last_item_required');
            }

            // This gateway currently returns 409 until Finance is installed.
            // Roll back the entire request, including any new Draft in POST.
            $this->finance->recordConfirmation($locked, $key, $depositAmount);

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
