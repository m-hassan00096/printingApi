<?php

namespace Modules\Identity\Actions\Users;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\Identity\Services\LastActiveAdminGuard;

class DeleteUserAction
{
    public function __construct(private readonly LastActiveAdminGuard $adminGuard) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->adminGuard->assertCanDelete($user);
            $user->delete();
        });
    }
}
