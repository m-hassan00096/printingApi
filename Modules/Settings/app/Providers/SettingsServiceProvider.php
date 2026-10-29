<?php

namespace Modules\Settings\Providers;

use App\Providers\ApiModuleServiceProvider;

class SettingsServiceProvider extends ApiModuleServiceProvider
{
    protected string $name = 'Settings';

    protected string $nameLower = 'settings';

    protected array $providers = [
        RouteServiceProvider::class,
        SettingsSelectServiceProvider::class,
        ParameterValueUsageServiceProvider::class,
        SettingsFinanceIntegrationServiceProvider::class,
        SettingsProductionIntegrationServiceProvider::class,
    ];
}
