<?php
namespace Modules\Deliveries\Providers;

use App\Providers\ApiModuleServiceProvider;

class DeliveriesServiceProvider extends ApiModuleServiceProvider
{
    protected string $name = 'Deliveries';
    protected string $nameLower = 'deliveries';
    protected array $providers = [RouteServiceProvider::class, DeliverySelectServiceProvider::class];
}
