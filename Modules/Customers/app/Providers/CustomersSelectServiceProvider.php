<?php
namespace Modules\Customers\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Models\User;
use Modules\Customers\Services\CustomerSelectService;

class CustomersSelectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('selects.orders.activeCustomers', static fn (User $user): bool => $user->canAny([
            'customers.selects.view',
            'orders.create',
            'orders.update',
        ]));

        Gate::define('selects.finance.customers', static fn (User $user): bool => $user->canAny([
            'customers.selects.view',
            'finance.collections.create',
        ]));

        SelectRegistry::register([
            'customers' => [CustomerSelectService::class, 'getAll', 'selects.finance.customers'],
            'activeCustomers' => [CustomerSelectService::class, 'getActive', 'selects.orders.activeCustomers'],
        ]);
    }
}
