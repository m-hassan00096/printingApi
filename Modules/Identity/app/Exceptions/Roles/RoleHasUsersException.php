<?php

namespace Modules\Identity\Exceptions\Roles;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class RoleHasUsersException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('role_management.role_has_users'),
            status: HttpStatusCode::CONFLICT,
        );
    }
}
