<?php
namespace Modules\Orders\Enums;

/** Production will implement all transitions in its own module. */
enum OrderItemStage: int
{
    case NOT_STARTED = 0;
    case DESIGN_IN_PROGRESS = 1;
    case DESIGN_COMPLETED = 2;
    case PRINTING = 3;
    case READY_FOR_DELIVERY = 4;
}
