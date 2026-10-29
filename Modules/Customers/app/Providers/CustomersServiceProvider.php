<?php
namespace Modules\Customers\Providers;

use App\Providers\ApiModuleServiceProvider;

class CustomersServiceProvider extends ApiModuleServiceProvider
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
