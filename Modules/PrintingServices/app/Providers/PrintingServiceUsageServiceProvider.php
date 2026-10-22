<?php

namespace Modules\PrintingServices\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PrintingServices\Services\PrintingServiceUsageRegistry;

class PrintingServiceUsageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PrintingServiceUsageRegistry::class);
    }
}

