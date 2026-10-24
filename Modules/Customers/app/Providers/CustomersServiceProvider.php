<?php
namespace Modules\Customers\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class CustomersServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Customers';
    protected string $nameLower = 'customers';

    protected array $providers = [
        RouteServiceProvider::class,
        CustomersLifecycleServiceProvider::class,
        CustomersSelectServiceProvider::class,
        CustomersFinanceIntegrationServiceProvider::class,
    ];
}
