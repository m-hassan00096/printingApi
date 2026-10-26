<?php

namespace Modules\Settings\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Models\User;
use Modules\Settings\Services\Selects\ParameterValueSelectService;
use Modules\Settings\Services\Selects\PaymentDestinationSelectService;

class SettingsSelectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Reading active customer types is required for customer forms, even
        // when staff have no permission to manage the Settings module.
        Gate::define('selects.customerTypes', static fn (User $user): bool => $user->canAny([
            'settings.selects.view',
            'customers.view',
            'customers.create',
            'customers.update',
            'printing_services.view',
            'printing_services.create',
            'printing_services.update',
            'printing_services.price_preview.view',
        ]));

        Gate::define('selects.orders.paymentDestinations', static fn (User $user): bool => $user->canAny([
            'settings.selects.view',
            'orders.confirm',
            'finance.collections.create',
            'finance.refunds.create',
        ]));

        SelectRegistry::register([
            'printingLocations' => [
                ParameterValueSelectService::class,
                'getPrintingLocations',
                'settings.selects.view',
            ],
            'customerTypes' => [
                ParameterValueSelectService::class,
                'getCustomerTypes',
                'selects.customerTypes',
            ],
            'bankAccounts' => [
                PaymentDestinationSelectService::class,
                'getBankAccounts',
                'selects.orders.paymentDestinations',
            ],
            'eWallets' => [
                PaymentDestinationSelectService::class,
                'getEWallets',
                'selects.orders.paymentDestinations',
            ],
        ]);
    }
}
