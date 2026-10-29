<?php

namespace Modules\PrintingServices\Providers;

use App\Providers\ApiModuleServiceProvider;

class PrintingServicesServiceProvider extends ApiModuleServiceProvider
{
    protected string $name = 'PrintingServices';

    protected string $nameLower = 'printing-services';

    protected array $providers = [
        RouteServiceProvider::class,
        PrintingServicesSelectServiceProvider::class,
        PrintingServicesParameterValueServiceProvider::class,
        PrintingServiceUsageServiceProvider::class,
    ];
}

