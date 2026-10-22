<?php
namespace Modules\Customers\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerLifecycleRegistry;

class DeleteCustomerAction
{
    public function __construct(private readonly CustomerLifecycleRegistry $lifecycle) {}

    public function execute(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            // Future Orders and Finance register their own historic-reference guards.
            $this->lifecycle->assertCanDelete($locked);

            // Free the exact phone for future registration while retaining history.
            $locked->live_phone_hash = null;
            $locked->save();
            $locked->delete();
        }, 3);
    }
}
