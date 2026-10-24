<?php
namespace Modules\Customers\Contracts;

/** Finance uses IDs/snapshots, not Customer Eloquent instances. */
interface CustomerFinanceReference
{
    public function assertExistsAndLock(int $customerId): void;

    /** @return array{id: int, name: string, phone: string} */
    public function display(int $customerId): array;
}
