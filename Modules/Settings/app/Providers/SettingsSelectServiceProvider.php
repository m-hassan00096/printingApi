<?php

namespace Modules\Settings\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Settings\Services\Selects\ParameterValueSelectService;
use Modules\Settings\Services\Selects\PaymentDestinationSelectService;

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
            'bankAccounts' => [
                PaymentDestinationSelectService::class,
                'getBankAccounts',
                'settings.selects.view',
            ],
            'eWallets' => [
                PaymentDestinationSelectService::class,
                'getEWallets',
                'settings.selects.view',
            ],
        ]);
    }
}
