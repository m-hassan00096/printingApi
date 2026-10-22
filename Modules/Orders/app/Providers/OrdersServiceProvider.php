<?php
namespace Modules\Orders\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class OrdersServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Orders';
    protected string $nameLower = 'orders';

    protected array $providers = [
        RouteServiceProvider::class,
        OrdersIntegrationServiceProvider::class,
    ];
}
