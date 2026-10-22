<?php

namespace Modules\Settings\Enums;

enum PaymentDestinationStatus: int
{
    case INACTIVE = 0;
    case ACTIVE = 1;
}
