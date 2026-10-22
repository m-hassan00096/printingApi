<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\RoleSeeder;

class SettingsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Business payment destinations are intentionally never seeded.
        $this->call([
            ParameterSeeder::class,
            SettingsPermissionSeeder::class,

            // Identity's admin role must receive permissions introduced by Settings.
            // Sync only after all Settings permissions have been created.
            RoleSeeder::class,
        ]);
    }
}
