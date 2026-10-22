<?php

namespace Modules\PrintingServices\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class PrintingServicesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'PrintingServices';

    protected string $nameLower = 'printing-services';

    protected array $providers = [
        RouteServiceProvider::class,
        PrintingServicesSelectServiceProvider::class,
    ];
}

