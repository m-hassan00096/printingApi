<?php
namespace Modules\Orders\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\RoleSeeder;

class OrdersDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([OrdersPermissionSeeder::class, RoleSeeder::class]);
    }
}
