<?php
namespace Modules\Customers\Contracts;

interface CustomerFinanceReference
{
    public function exists(int $customerId): bool;

    public function assertExistsAndLock(int $customerId): void;

    /** @return array{id: int, name: string, phone: string} */
    public function display(int $customerId): array;
}
