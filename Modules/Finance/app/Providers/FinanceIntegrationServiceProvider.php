<?php
namespace Modules\Finance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Customers\Services\CustomerLifecycleRegistry;
use Modules\Finance\Services\FinanceCustomerUsageGuard;
use Modules\Finance\Services\FinanceOrderGateway;
use Modules\Orders\Services\OrderFinanceGateway;

class FinanceIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderFinanceGateway::class, FinanceOrderGateway::class);
    }

    public function boot(CustomerLifecycleRegistry $customers): void
    {
        $customers->registerUsageGuard(FinanceCustomerUsageGuard::class);
    }
}
