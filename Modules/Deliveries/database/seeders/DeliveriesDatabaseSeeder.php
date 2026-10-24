<?php
namespace Modules\Deliveries\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\RoleSeeder;

class DeliveriesDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([DeliveriesPermissionSeeder::class, RoleSeeder::class]);
    }
}
