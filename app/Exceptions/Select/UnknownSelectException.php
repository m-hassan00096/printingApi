<?php

namespace App\Exceptions\Select;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class UnknownSelectException extends BusinessException
{
    public function __construct(string $key)
    {
        $message = __('selects.unknown', ['key' => $key]);

        parent::__construct(
            message: $message,
            status: HttpStatusCode::UNPROCESSABLE_ENTITY,
            errors: ['selects' => [$message]],
        );
    }
}
