<?php

namespace Modules\Identity\Exceptions\Roles;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class ProtectedRoleException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('role_management.admin_protected'),
            status: HttpStatusCode::CONFLICT,
        );
    }
}
