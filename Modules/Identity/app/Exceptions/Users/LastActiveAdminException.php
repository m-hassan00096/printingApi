<?php

namespace Modules\Identity\Exceptions\Users;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class LastActiveAdminException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('identity.last_active_admin'),
            status: HttpStatusCode::CONFLICT,
        );
    }
}
