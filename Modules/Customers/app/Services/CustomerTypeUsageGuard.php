<?php
namespace Modules\Customers\Services;

use Modules\Customers\Exceptions\CustomerTypeInUseException;
use Modules\Customers\Models\Customer;
use Modules\Settings\Contracts\ParameterValueUsageGuard;
use Modules\Settings\Models\ParameterValue;

class CustomerTypeUsageGuard implements ParameterValueUsageGuard
{
    public function assertCanDelete(ParameterValue $value): void
    {
        if ($value->parameter()->value('name') !== 'customer_types') {
            return;
        }

        // A never-used Soft Deleted customer does not reserve its former type.
        if (Customer::query()->where('customer_type_id', $value->getKey())->exists()) {
            throw new CustomerTypeInUseException();
        }
    }
}
