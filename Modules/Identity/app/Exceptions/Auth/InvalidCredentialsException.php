<?php

namespace Modules\Identity\Exceptions\Auth;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

final class InvalidCredentialsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('auth.failed'),
            status: HttpStatusCode::UNPROCESSABLE_ENTITY,
            errors: [],
        );
    }
}
