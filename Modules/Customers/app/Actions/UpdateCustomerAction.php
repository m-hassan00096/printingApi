<?php
namespace Modules\Customers\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerLifecycleRegistry;
use Modules\Customers\Services\CustomerTypeAssignmentService;
use Modules\Customers\Support\CustomerPhone;

class UpdateCustomerAction
{
    public function __construct(
        private readonly CustomerTypeAssignmentService $types,
        private readonly CustomerLifecycleRegistry $lifecycle,
    ) {}

    public function execute(Customer $customer, array $data): void
    {
        try {
            DB::transaction(function () use ($customer, $data): void {
                $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

                if (array_key_exists('customerTypeId', $data)) {
                    if ($data['customerTypeId'] !== null) {
                        $this->types->assertEligibleAndLock((int) $data['customerTypeId']);
                    }
                    $locked->customer_type_id = $data['customerTypeId'] === null ? null : (int) $data['customerTypeId'];
                }

                if ($locked->status === CustomerStatus::ACTIVE
                    && array_key_exists('status', $data)
                    && (int) $data['status'] === CustomerStatus::INACTIVE->value) {
                    $this->lifecycle->assertCanDeactivate($locked);
                }

                foreach (['name', 'phone', 'address', 'status'] as $attribute) {
                    if (array_key_exists($attribute, $data)) {
                        $locked->{$attribute} = $data[$attribute];
                    }
                }

                if (array_key_exists('phone', $data)) {
                    $locked->live_phone_hash = CustomerPhone::hash((string) $data['phone']);
                }

                if ($locked->isDirty()) {
                    $locked->save();
                }
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'phone' => __('customers.messages.duplicate_phone'),
            ]);
        }
    }
}
