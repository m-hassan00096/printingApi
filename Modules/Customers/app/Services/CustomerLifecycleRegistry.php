<?php
namespace Modules\Customers\Services;

use InvalidArgumentException;
use Modules\Customers\Contracts\CustomerStatusTransitionGuard;
use Modules\Customers\Contracts\CustomerUsageGuard;
use Modules\Customers\Models\Customer;

final class CustomerLifecycleRegistry
{
    /** @var array<class-string<CustomerUsageGuard>, true> */
    private array $usageGuards = [];

    /** @var array<class-string<CustomerStatusTransitionGuard>, true> */
    private array $deactivationGuards = [];

    /** @param class-string<CustomerUsageGuard> $guardClass */
    public function registerUsageGuard(string $guardClass): void
    {
        if (! is_a($guardClass, CustomerUsageGuard::class, true)) {
            throw new InvalidArgumentException(__('customers.messages.invalid_usage_guard', ['guard' => $guardClass]));
        }

        $this->usageGuards[$guardClass] = true;
    }

    /** @param class-string<CustomerStatusTransitionGuard> $guardClass */
    public function registerDeactivationGuard(string $guardClass): void
    {
        if (! is_a($guardClass, CustomerStatusTransitionGuard::class, true)) {
            throw new InvalidArgumentException(__('customers.messages.invalid_deactivation_guard', ['guard' => $guardClass]));
        }

        $this->deactivationGuards[$guardClass] = true;
    }

    public function assertCanDelete(Customer $customer): void
    {
        foreach (array_keys($this->usageGuards) as $guardClass) {
            app($guardClass)->assertCanDelete($customer);
        }
    }

    public function assertCanDeactivate(Customer $customer): void
    {
        foreach (array_keys($this->deactivationGuards) as $guardClass) {
            app($guardClass)->assertCanDeactivate($customer);
        }
    }
}
