<?php
namespace Modules\Orders\Contracts;

/** Finance may validate a reference but cannot mutate Orders via this interface. */
interface OrderFinanceReference
{
    public function assertCustomerOwnsOrder(int $orderId, int $customerId): void;

    /** @return list<int> */
    public function findOrderIdsByNumber(string $search): array;
}
