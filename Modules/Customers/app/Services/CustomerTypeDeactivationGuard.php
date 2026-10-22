<?php
namespace Modules\Customers\Services;

use Modules\Customers\Exceptions\CustomerTypeInUseException;
use Modules\Customers\Models\Customer;
use Modules\Settings\Contracts\ParameterValueDeactivationGuard;
use Modules\Settings\Models\ParameterValue;

class CustomerTypeDeactivationGuard implements ParameterValueDeactivationGuard
{
    public function assertCanDeactivate(ParameterValue $value): void
    {
        if ($value->parameter()->value('name') !== 'customer_types') {
            return;
        }

        if (Customer::query()->where('customer_type_id', $value->getKey())->exists()) {
            throw new CustomerTypeInUseException();
        }
    }
}
