<?php

namespace App\Exceptions\Files;

use App\Enums\HttpStatusCode;
use App\Exceptions\BusinessException;

class FileUploadException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            message: __('files.upload_failed'),
            status: HttpStatusCode::INTERNAL_SERVER_ERROR,
        );
    }
}
