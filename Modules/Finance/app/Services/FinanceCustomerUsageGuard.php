<?php
namespace Modules\Finance\Services;

use Modules\Customers\Contracts\CustomerUsageGuard;
use Modules\Customers\Exceptions\CustomerInUseException;
use Modules\Customers\Models\Customer;
use Modules\Finance\Models\LedgerEntry;

/** Customer registry adapter only. Finance business logic accepts IDs, not Customer models. */
class FinanceCustomerUsageGuard implements CustomerUsageGuard
{
    public function assertCanDelete(Customer $customer): void
    {
        if (LedgerEntry::query()->where('customer_id', $customer->getKey())->exists()) {
            throw new CustomerInUseException();
        }
    }
}
