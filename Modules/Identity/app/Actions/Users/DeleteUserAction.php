<?php

namespace Modules\Identity\Actions\Users;

use Modules\Identity\Models\User;

class DeleteUserAction
{
    public function execute(User $user): void
    {
        // The User model uses SoftDeletes.
        $user->delete();
    }
}
