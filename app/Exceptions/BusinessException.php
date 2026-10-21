<?php

namespace App\Exceptions;

use App\Enums\HttpStatusCode;
use RuntimeException;

abstract class BusinessException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly HttpStatusCode $status = HttpStatusCode::UNPROCESSABLE_ENTITY,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
