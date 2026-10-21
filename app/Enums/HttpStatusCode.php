<?php

namespace App\Enums;

enum HttpStatusCode: int
{
    case OK = 200;
    case CREATED = 201;
    case NO_CONTENT = 204;

    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case CONFLICT = 409;
    case UNPROCESSABLE_ENTITY = 422;
    case TOO_MANY_REQUESTS = 429;

    case INTERNAL_SERVER_ERROR = 500;
}
