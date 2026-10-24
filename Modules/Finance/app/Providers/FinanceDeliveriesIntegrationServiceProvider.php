<?php
namespace Modules\Finance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Deliveries\Contracts\DeliveryFinanceGateway;
use Modules\Finance\Services\FinanceDeliveryAdapter;

class FinanceDeliveriesIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliveryFinanceGateway::class, FinanceDeliveryAdapter::class);
    }
}
