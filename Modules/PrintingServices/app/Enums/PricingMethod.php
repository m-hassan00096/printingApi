<?php

namespace Modules\PrintingServices\Enums;

enum PricingMethod: string
{
    case QUANTITY = 'quantity';
    case SQUARE_CENTIMETER = 'square_centimeter';
    case SQUARE_METER = 'square_meter';
}
