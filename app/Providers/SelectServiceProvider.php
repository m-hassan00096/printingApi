<?php

namespace App\Providers;

use App\Services\Select\SelectRegistry;
use Illuminate\Support\ServiceProvider;

class SelectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SelectRegistry::class);
    }
}
