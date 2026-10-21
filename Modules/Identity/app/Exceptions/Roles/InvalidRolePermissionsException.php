<?php

namespace Modules\Identity\Exceptions\Roles;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class InvalidRolePermissionsException extends BusinessException
{
    public function __construct()
    {
        $message = __('role_management.invalid_permissions');
        parent::__construct(
            message: $message,
            status: HttpStatusCode::UNPROCESSABLE_ENTITY,
            errors: ['permissionIds' => [$message]],
        );
    }
}
