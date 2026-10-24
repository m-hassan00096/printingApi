<?php
namespace Modules\Settings\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Settings\Contracts\PaymentDestinationLookup;
use Modules\Settings\Services\PaymentDestinationResolver;

class SettingsFinanceIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentDestinationLookup::class, PaymentDestinationResolver::class);
    }
}
