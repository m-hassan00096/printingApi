<?php

namespace Modules\Identity\Enums;

enum RoleStatus: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;

    public function label(): string
    {
        return match ($this) {
            self::INACTIVE => __('enums.role_status.inactive'),
            self::ACTIVE => __('enums.role_status.active'),
        };
    }
}
