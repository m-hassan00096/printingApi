<?php
namespace Modules\Customers\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\RoleSeeder;

class CustomersDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CustomersPermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
