<?php
namespace Modules\Orders\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Deliveries\Contracts\DeliveryOrderGateway;
use Modules\Orders\Services\OrdersDeliveryAdapter;

class OrdersDeliveriesIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliveryOrderGateway::class, OrdersDeliveryAdapter::class);
    }
}
