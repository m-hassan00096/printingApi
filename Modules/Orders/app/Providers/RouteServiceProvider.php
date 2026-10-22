<?php
namespace Modules\Orders\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Orders';

    public function map(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
