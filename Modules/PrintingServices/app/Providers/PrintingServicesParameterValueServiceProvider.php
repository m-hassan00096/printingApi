<?php

namespace Modules\PrintingServices\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PrintingServices\Services\CustomerTypeUsageGuard;
use Modules\Settings\Services\ParameterValueUsageRegistry;

class PrintingServicesParameterValueServiceProvider extends ServiceProvider
{
    public function boot(ParameterValueUsageRegistry $registry): void
    {
        // Explicit extension point: Settings knows only the contract/registry.
        $registry->register(CustomerTypeUsageGuard::class);
    }
}

