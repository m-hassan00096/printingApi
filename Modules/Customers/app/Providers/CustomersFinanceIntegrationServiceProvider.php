<?php
namespace Modules\Customers\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Customers\Services\CustomerFinanceReferenceService;

class CustomersFinanceIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerFinanceReference::class, CustomerFinanceReferenceService::class);
    }
}
