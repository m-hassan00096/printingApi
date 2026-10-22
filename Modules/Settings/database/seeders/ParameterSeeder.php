<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Settings\Models\Parameter;

class ParameterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['printing_locations', 'customer_types'] as $name) {
            Parameter::query()->firstOrCreate(['name' => $name]);
        }
    }
}
