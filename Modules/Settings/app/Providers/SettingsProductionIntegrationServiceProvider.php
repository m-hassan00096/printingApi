<?php
namespace Modules\Settings\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Settings\Contracts\PrintingLocationLookup;
use Modules\Settings\Services\PrintingLocationResolver;

class SettingsProductionIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PrintingLocationLookup::class, PrintingLocationResolver::class);
    }
}
