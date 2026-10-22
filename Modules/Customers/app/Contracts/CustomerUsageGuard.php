<?php
namespace Modules\Customers\Contracts;

use Modules\Customers\Models\Customer;

/** Implemented by Orders/Finance when they persist customer-linked records. */
interface CustomerUsageGuard
{
    public function assertCanDelete(Customer $customer): void;
}
