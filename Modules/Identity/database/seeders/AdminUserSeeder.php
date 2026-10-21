<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $role = Role::query()
                ->where('name', 'admin')
                ->where('guard_name', 'web')
                ->firstOrFail();

            $user = User::firstOrCreate(
                ['username' => 'admin'],
                [
                    'name' => 'System Administrator',
                    'password' => 'mans123456',
                    'status' => UserStatus::ACTIVE,
                ]
            );

            $user->syncRoles([$role]);
        });
    }
}
