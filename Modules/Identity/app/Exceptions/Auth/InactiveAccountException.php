<?php

namespace Modules\Identity\Exceptions\Auth;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

final class InactiveAccountException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('auth.inactive'),
            status: HttpStatusCode::FORBIDDEN,
        );
    }
}
