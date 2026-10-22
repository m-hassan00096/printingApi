<?php
namespace Modules\Orders\Services;

use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;

class UnavailableOrderFinanceGateway implements OrderFinanceGateway
{
    public function recordConfirmation(Order $order, string $idempotencyKey, ?string $depositAmount): void
    {
        throw new OrdersBusinessException('finance_unavailable');
    }

    public function recordCancellation(Order $order, string $idempotencyKey): void
    {
        throw new OrdersBusinessException('finance_unavailable');
    }
}
