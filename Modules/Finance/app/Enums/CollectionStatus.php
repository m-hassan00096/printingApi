<?php
namespace Modules\Finance\Enums;

enum CollectionStatus: int
{
    case POSTED = 1;
    case REVERSED = 2;

    public function label(): string
    {
        return __('finance.collection_statuses.'.match ($this) {
            self::POSTED => 'posted',
            self::REVERSED => 'reversed',
        });
    }
}
