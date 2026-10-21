<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;
use App\Services\Select\SelectRegistry as Registry;

class SelectRegistry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Registry::class;
    }
}
