<?php
namespace Modules\Orders\Contracts;

interface OrderFinanceReference
{
    public function belongsToCustomer(int $orderId, int $customerId): bool;

    /** @return list<int> */
    public function findOrderIdsByNumber(string $search): array;
}
