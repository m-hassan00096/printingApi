<?php
namespace Modules\Customers\Contracts;

use Modules\Customers\Models\Customer;

/** Implemented by Orders to prohibit deactivation when any historic order exists. */
interface CustomerStatusTransitionGuard
{
    public function assertCanDeactivate(Customer $customer): void;
}
