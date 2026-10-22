<?php

namespace Modules\PrintingServices\Services;

use Modules\PrintingServices\Exceptions\CustomerTypeInUseException;
use Modules\PrintingServices\Models\PrintingServicePriceTier;
use Modules\Settings\Contracts\ParameterValueUsageGuard;
use Modules\Settings\Models\ParameterValue;

final class CustomerTypeUsageGuard implements ParameterValueUsageGuard
{
    public function assertCanDelete(ParameterValue $value): void
    {
        if ($value->parameter()->value('name') !== 'customer_types') {
            return;
        }

        // Soft-deleted price tiers still reference the historical customer type.
        // The physical FK alone cannot protect a soft-deleted ParameterValue.
        if (PrintingServicePriceTier::withTrashed()
            ->where('customer_type_id', $value->getKey())
            ->exists()) {
            throw new CustomerTypeInUseException();
        }
    }
}

