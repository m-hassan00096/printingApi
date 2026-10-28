<?php
namespace Modules\Customers\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerTypeAssignmentService;
use Modules\Customers\Support\CustomerPhone;

class CreateCustomerAction
{
    public function __construct(private readonly CustomerTypeAssignmentService $types) {}

    public function execute(array $data): Customer
    {
        try {
            return DB::transaction(function () use ($data): Customer {
                if (isset($data['customerTypeId'])) {
                    $this->types->assertEligibleAndLock((int) $data['customerTypeId']);
                }

                $customer = new Customer();
                $customer->fill(Arr::only($data, ['name', 'phone', 'address', 'status']));
                $customer->customer_type_id = isset($data['customerTypeId']) ? (int) $data['customerTypeId'] : null;
                $customer->live_phone_hash = CustomerPhone::hash($customer->phone);
                $customer->save();

                return $customer;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'phone' => __('customers.messages.duplicate_phone'),
            ]);
        }
    }
}
