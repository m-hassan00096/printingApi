<?php

namespace Modules\Settings\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Settings\Services\Selects\ParameterValueSelectService;

class SettingsSelectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SelectRegistry::register([
            'printingLocations' => [
                ParameterValueSelectService::class,
                'getPrintingLocations',
                'settings.selects.view',
            ],
            'customerTypes' => [
                ParameterValueSelectService::class,
                'getCustomerTypes',
                'settings.selects.view',
            ],
        ]);
    }
}
