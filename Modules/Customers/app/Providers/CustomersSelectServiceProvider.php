<?php
namespace Modules\Customers\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Customers\Services\CustomerSelectService;

class CustomersSelectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SelectRegistry::register([
            'customers' => [CustomerSelectService::class, 'getAll', 'customers.selects.view'],
            'activeCustomers' => [CustomerSelectService::class, 'getActive', 'customers.selects.view'],
        ]);
    }
}
