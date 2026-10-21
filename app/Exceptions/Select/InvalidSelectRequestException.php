<?php

namespace App\Exceptions\Select;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class InvalidSelectRequestException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('selects.invalid_request'),
            status: HttpStatusCode::UNPROCESSABLE_ENTITY,
            errors: ['selects' => [__('selects.invalid_request')]],
        );
    }
}
