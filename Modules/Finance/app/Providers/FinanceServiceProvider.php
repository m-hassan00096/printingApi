<?php
namespace Modules\Finance\Providers;

use App\Providers\ApiModuleServiceProvider;

class FinanceServiceProvider extends ApiModuleServiceProvider
{
    protected string $name = 'Finance';
    protected string $nameLower = 'finance';

    protected array $providers = [
        RouteServiceProvider::class,
        FinanceIntegrationServiceProvider::class,
        FinanceDeliveriesIntegrationServiceProvider::class,
    ];
}
