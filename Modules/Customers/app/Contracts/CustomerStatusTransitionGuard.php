<?php
namespace Modules\Customers\Contracts;

use Modules\Customers\Models\Customer;

/** Implemented by Orders to prohibit deactivation while non-cancelled orders are undelivered. */
interface CustomerStatusTransitionGuard
{
    public function assertCanDeactivate(Customer $customer): void;
}
