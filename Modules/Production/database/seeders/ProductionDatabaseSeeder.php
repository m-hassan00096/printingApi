<?php
namespace Modules\Production\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\RoleSeeder;

class ProductionDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ProductionPermissionSeeder::class, RoleSeeder::class]);
    }
}
