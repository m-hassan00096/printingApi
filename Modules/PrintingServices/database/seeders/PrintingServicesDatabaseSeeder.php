<?php

namespace Modules\PrintingServices\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\RoleSeeder;

class PrintingServicesDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PrintingServicesPermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}

