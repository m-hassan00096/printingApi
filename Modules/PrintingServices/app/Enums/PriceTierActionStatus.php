<?php

namespace Modules\PrintingServices\Enums;

enum PriceTierActionStatus: int
{
    case UNCHANGED = 0;
    case CREATE = 1;
    case UPDATE = 2;
    case DELETE = 3;
}

