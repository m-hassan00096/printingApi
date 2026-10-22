<?php
namespace Modules\Customers\Tests\Feature\Support;

use Modules\Customers\Contracts\CustomerStatusTransitionGuard;
use Modules\Customers\Contracts\CustomerUsageGuard;
use Modules\Customers\Exceptions\CustomerHasUndeliveredOrdersException;
use Modules\Customers\Exceptions\CustomerInUseException;
use Modules\Customers\Models\Customer;

/** Test-only proxy for future Orders/Finance adapters. No fake production tables. */
class FakeCustomerUsageGuard implements CustomerUsageGuard
{
    public function assertCanDelete(Customer $customer): void
    {
        if ($customer->name === 'Has History') {
            throw new CustomerInUseException();
        }
    }
}

/** Test-only proxy for future Orders pending-order check. */
class FakeCustomerStatusTransitionGuard implements CustomerStatusTransitionGuard
{
    public function assertCanDeactivate(Customer $customer): void
    {
        if ($customer->name === 'Has Open Order') {
            throw new CustomerHasUndeliveredOrdersException();
        }
    }
}
