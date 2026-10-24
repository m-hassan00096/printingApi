<?php
namespace Modules\Orders\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Orders\Services\OrdersProductionAdapter;
use Modules\Production\Contracts\ProductionOrderGateway;

class OrdersProductionIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductionOrderGateway::class, OrdersProductionAdapter::class);
    }
}
