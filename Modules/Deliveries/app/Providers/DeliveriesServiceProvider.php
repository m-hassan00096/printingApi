<?php
namespace Modules\Deliveries\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class DeliveriesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Deliveries';
    protected string $nameLower = 'deliveries';
    protected array $providers = [RouteServiceProvider::class, DeliverySelectServiceProvider::class];
}
