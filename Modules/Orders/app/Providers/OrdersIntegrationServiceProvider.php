<?php
namespace Modules\Orders\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Customers\Services\CustomerLifecycleRegistry;
use Modules\Orders\Services\OrderCustomerDeactivationGuard;
use Modules\Orders\Services\OrderCustomerUsageGuard;
use Modules\Orders\Services\OrderFinanceGateway;
use Modules\Orders\Services\OrderPrintingServiceUsageGuard;
use Modules\Orders\Services\UnavailableOrderFinanceGateway;
use Modules\PrintingServices\Services\PrintingServiceUsageRegistry;

class OrdersIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderFinanceGateway::class, UnavailableOrderFinanceGateway::class);
    }

    public function boot(CustomerLifecycleRegistry $customers, PrintingServiceUsageRegistry $services): void
    {
        $customers->registerUsageGuard(OrderCustomerUsageGuard::class);
        $customers->registerDeactivationGuard(OrderCustomerDeactivationGuard::class);
        $services->register(OrderPrintingServiceUsageGuard::class);
    }
}
