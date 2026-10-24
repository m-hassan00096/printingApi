<?php
namespace Modules\Orders\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Orders\Contracts\OrderFinanceReference;
use Modules\Orders\Services\OrderFinanceReferenceService;

class OrdersFinanceReferenceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderFinanceReference::class, OrderFinanceReferenceService::class);
    }
}
