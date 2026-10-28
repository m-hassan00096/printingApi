<?php
namespace Modules\Deliveries\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Deliveries\Services\DeliveryCustomerSelectService;
use Modules\Identity\Models\User;

class DeliverySelectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('selects.deliveries.customers', static fn (User $user): bool => $user->canAny([
            'deliveries.ready.view', 'deliveries.list',
        ]));

        SelectRegistry::register([
            'deliveryCustomers' => [
                DeliveryCustomerSelectService::class, 'options', 'selects.deliveries.customers',
            ],
        ]);
    }
}
