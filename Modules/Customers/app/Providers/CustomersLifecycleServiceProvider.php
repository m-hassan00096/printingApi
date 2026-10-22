<?php
namespace Modules\Customers\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Customers\Services\CustomerLifecycleRegistry;
use Modules\Customers\Services\CustomerTypeDeactivationGuard;
use Modules\Customers\Services\CustomerTypeUsageGuard;
use Modules\Settings\Services\ParameterValueUsageRegistry;

class CustomersLifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CustomerLifecycleRegistry::class);
    }

    public function boot(ParameterValueUsageRegistry $registry): void
    {
        $registry->register(CustomerTypeUsageGuard::class);
        $registry->registerDeactivationGuard(CustomerTypeDeactivationGuard::class);
    }
}
