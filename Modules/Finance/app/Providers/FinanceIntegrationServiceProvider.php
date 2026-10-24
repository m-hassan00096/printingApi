<?php
namespace Modules\Finance\Providers;

use Illuminate\Support\ServiceProvider;

/** Bind consumer-owned gateway interfaces to Finance adapters when integrated. */
class FinanceIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void {}
}
