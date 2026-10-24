<?php
namespace Modules\Production\Enums;

enum ProductionEventType: int
{
    case DESIGN_STARTED = 1;
    case DESIGN_COMPLETED = 2;
    case SENT_TO_PRINTING = 3;
    case PRINTING_LOCATION_CHANGED = 4;
    case PRINTING_COMPLETED = 5;
    case UNDONE = 6;

    public function label(): string
    {
        return __('production.events.'.match ($this) {
            self::DESIGN_STARTED => 'design_started',
            self::DESIGN_COMPLETED => 'design_completed',
            self::SENT_TO_PRINTING => 'sent_to_printing',
            self::PRINTING_LOCATION_CHANGED => 'printing_location_changed',
            self::PRINTING_COMPLETED => 'printing_completed',
            self::UNDONE => 'undone',
        });
    }
}
