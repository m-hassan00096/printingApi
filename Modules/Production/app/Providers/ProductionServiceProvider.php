<?php
namespace Modules\Production\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ProductionServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Production';
    protected string $nameLower = 'production';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
