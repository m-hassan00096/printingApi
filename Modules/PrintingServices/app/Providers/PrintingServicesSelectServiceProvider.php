<?php

namespace Modules\PrintingServices\Providers;

use App\Facades\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Models\User;
use Modules\PrintingServices\Services\PrintingServiceSelectService;

class PrintingServicesSelectServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('selects.orders.printingServices', static fn (User $user): bool => $user->canAny([
            'printing_services.selects.view',
            'orders.create',
            'orders.items.create',
            'orders.items.update',
        ]));

        SelectRegistry::register([
            'printingServices' => [
                PrintingServiceSelectService::class,
                'getActive',
                'selects.orders.printingServices',
            ],
        ]);
    }
}

