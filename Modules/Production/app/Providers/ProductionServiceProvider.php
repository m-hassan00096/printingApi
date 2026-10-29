<?php
namespace Modules\Production\Providers;

use App\Providers\ApiModuleServiceProvider;

class ProductionServiceProvider extends ApiModuleServiceProvider
{
    protected string $name = 'Production';
    protected string $nameLower = 'production';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
