<?php
namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\RoleSeeder;

class FinanceDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([FinancePermissionSeeder::class, RoleSeeder::class]);
    }
}
