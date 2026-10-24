<?php
namespace Modules\Orders\Services;

use Modules\Orders\Contracts\ConfirmOrderFinanceData;
use Modules\Orders\Contracts\CancelOrderFinanceData;

/** Consumer-owned boundary. Caller runs within the Orders status transaction. */
interface OrderFinanceGateway
{
    public function recordConfirmation(ConfirmOrderFinanceData $data): void;

    public function recordCancellation(CancelOrderFinanceData $data): void;
}
