<?php

namespace App\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

abstract class ApiModuleServiceProvider extends ModuleServiceProvider
{
    protected function registerViews(): void
    {
        // API-only modules may not have Blade views. Avoid registering missing
        // paths, as Laravel's view:cache command requires them to exist.
        $viewsPath = module_path(
            $this->name,
            config('modules.paths.generator.views.path', 'resources/views'),
        );

        if (! is_dir($viewsPath)) {
            return;
        }

        parent::registerViews();
    }
}
