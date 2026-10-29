<?php

use App\Providers\AppServiceProvider;
use App\Providers\SelectServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

return [
    AppServiceProvider::class,
    PermissionServiceProvider::class,
    SelectServiceProvider::class,
];
