<?php

namespace Modules\Settings\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Settings\Services\ParameterValueUsageRegistry;

class ParameterValueUsageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ParameterValueUsageRegistry::class);
    }
}

