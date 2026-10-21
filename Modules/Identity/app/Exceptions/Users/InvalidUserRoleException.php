<?php

namespace Modules\Identity\Exceptions\Users;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class InvalidUserRoleException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('identity.invalid_role'),
            status: HttpStatusCode::UNPROCESSABLE_ENTITY,
            errors: ['roleId' => [__('identity.invalid_role')]],
        );
    }
}
