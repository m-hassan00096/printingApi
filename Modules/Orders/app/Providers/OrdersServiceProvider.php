<?php
namespace Modules\Orders\Providers;

use App\Providers\ApiModuleServiceProvider;

class OrdersServiceProvider extends ApiModuleServiceProvider
{
    protected string $name = 'Orders';
    protected string $nameLower = 'orders';

    protected array $providers = [
        RouteServiceProvider::class,
        OrdersIntegrationServiceProvider::class,
        OrdersFinanceReferenceProvider::class,
        OrdersProductionIntegrationServiceProvider::class,
        OrdersDeliveriesIntegrationServiceProvider::class,
    ];
}
