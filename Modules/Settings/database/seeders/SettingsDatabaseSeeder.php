<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;

class SettingsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Business payment destinations are intentionally never seeded.
        $this->call([
            ParameterSeeder::class,
            SettingsPermissionSeeder::class,
        ]);
    }
}
