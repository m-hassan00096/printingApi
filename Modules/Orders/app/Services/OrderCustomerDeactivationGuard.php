<?php
namespace Modules\Orders\Services;

use Modules\Customers\Contracts\CustomerStatusTransitionGuard;
use Modules\Customers\Models\Customer;
use Modules\Orders\Exceptions\OrdersBusinessException;
use Modules\Orders\Models\Order;

class OrderCustomerDeactivationGuard implements CustomerStatusTransitionGuard
{
    public function assertCanDeactivate(Customer $customer): void
    {
        // Owner-approved stronger rule: ANY persisted order blocks deactivation.
        if (Order::withTrashed()->where('customer_id', $customer->getKey())->exists()) {
            throw new OrdersBusinessException('customer_has_orders');
        }
    }
}
