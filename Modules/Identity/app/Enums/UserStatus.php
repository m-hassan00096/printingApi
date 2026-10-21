<?php

namespace Modules\Identity\Enums;
enum UserStatus: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;

    public function label(): string
    {
        return match ($this) {
            self::INACTIVE => __('enums.user_status.inactive'),
            self::ACTIVE => __('enums.user_status.active'),
        };
    }
}
