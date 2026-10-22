<?php
namespace Modules\Orders\Services;

use Modules\Orders\Models\Order;

/** Finance must post the immutable ledger inside the same DB transaction as status changes. */
interface OrderFinanceGateway
{
    public function recordConfirmation(Order $order, string $idempotencyKey, ?string $depositAmount): void;

    public function recordCancellation(Order $order, string $idempotencyKey): void;
}
