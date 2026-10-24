<?php
namespace Modules\Orders\Services;

use Modules\Orders\Contracts\ConfirmOrderFinanceData;
use Modules\Orders\Contracts\CancelOrderFinanceData;
use Modules\Orders\Exceptions\OrdersBusinessException;

class UnavailableOrderFinanceGateway implements OrderFinanceGateway
{
    public function recordConfirmation(ConfirmOrderFinanceData $data): void
    {
        throw new OrdersBusinessException('finance_unavailable');
    }

    public function recordCancellation(CancelOrderFinanceData $data): void
    {
        throw new OrdersBusinessException('finance_unavailable');
    }
}
