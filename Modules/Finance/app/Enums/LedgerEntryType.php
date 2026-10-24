<?php
namespace Modules\Finance\Enums;

enum LedgerEntryType: int
{
    case ORDER_DEBT = 1;
    case COLLECTION = 2;
    case ORDER_CANCELLATION = 3;
    case COLLECTION_REVERSAL = 4;
    case REFUND = 5;
    case OPENING_BALANCE = 6;

    public function label(): string
    {
        return __('finance.ledger_types.'.match ($this) {
            self::ORDER_DEBT => 'order_debt',
            self::COLLECTION => 'collection',
            self::ORDER_CANCELLATION => 'order_cancellation',
            self::COLLECTION_REVERSAL => 'collection_reversal',
            self::REFUND => 'refund',
            self::OPENING_BALANCE => 'opening_balance',
        });
    }
}
