<?php
namespace Modules\Customers\Services;

use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Customers\Models\Customer;

class CustomerFinanceReferenceService implements CustomerFinanceReference
{
    public function exists(int $customerId): bool
    {
        return Customer::query()->whereKey($customerId)->exists();
    }

    public function assertExistsAndLock(int $customerId): void
    {
        Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
    }

    public function display(int $customerId): array
    {
        $customer = Customer::query()->whereKey($customerId)->firstOrFail();
        return ['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone];
    }
}
