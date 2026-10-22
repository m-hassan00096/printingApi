<?php
namespace Modules\Customers\Services;

use Illuminate\Validation\ValidationException;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\ParameterValue;

class CustomerTypeAssignmentService
{
    /** Call inside a transaction. The Settings delete/deactivation actions lock this same row. */
    public function assertEligibleAndLock(int $customerTypeId): void
    {
        $type = ParameterValue::query()
            ->whereKey($customerTypeId)
            ->lockForUpdate()
            ->first();

        if ($type === null
            || $type->status !== ParameterValueStatus::ACTIVE
            || $type->parameter()->value('name') !== 'customer_types') {
            throw ValidationException::withMessages([
                'customerTypeId' => __('customers.messages.invalid_customer_type'),
            ]);
        }
    }
}
