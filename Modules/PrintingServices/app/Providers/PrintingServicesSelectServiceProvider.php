<?php

namespace Modules\PrintingServices\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\PrintingServices\Services\PrintingServiceSelectService;

class PrintingServicesSelectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SelectRegistry::register([
            'printingServices' => [
                PrintingServiceSelectService::class,
                'getActive',
                'printing_services.selects.view',
            ],
        ]);
    }
}

