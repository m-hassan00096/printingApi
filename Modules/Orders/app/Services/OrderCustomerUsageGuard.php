<?php
namespace Modules\Orders\Services;

use Modules\Customers\Contracts\CustomerUsageGuard;
use Modules\Customers\Exceptions\CustomerInUseException;
use Modules\Customers\Models\Customer;
use Modules\Orders\Models\Order;

class OrderCustomerUsageGuard implements CustomerUsageGuard
{
    public function assertCanDelete(Customer $customer): void
    {
        // Draft, cancelled, delivered and soft-deleted orders all count as historic references.
        if (Order::withTrashed()->where('customer_id', $customer->getKey())->exists()) {
            throw new CustomerInUseException();
        }
    }
}
