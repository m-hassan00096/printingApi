<?php
namespace Modules\Finance\Services;

use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Support\Money;
use Modules\Orders\Contracts\ConfirmOrderFinanceData;
use Modules\Orders\Contracts\CancelOrderFinanceData;
use Modules\Orders\Services\OrderFinanceGateway;

class FinanceOrderGateway implements OrderFinanceGateway
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly CollectionService $collections,
    ) {}

    public function recordConfirmation(ConfirmOrderFinanceData $data): void
    {
        $this->ledger->lockCustomer($data->customerId);
        $total = Money::cents($data->totalAmount);
        if ($total < 0) {
            throw new FinanceException('amount_out_of_range');
        }
        $this->ledger->post($data->customerId, LedgerEntryType::ORDER_DEBT,
            $total, 'order-confirm:'.$data->orderId, orderId: $data->orderId);

        if ($data->depositAmount !== null && Money::cents($data->depositAmount) > 0) {
            if ($data->paymentMethod === null) {
                throw new FinanceException('invalid_destination');
            }
            $this->collections->deposit(
                $data->orderId, $data->customerId, $data->depositAmount,
                $data->paymentMethod, $data->paymentDestinationId, $data->actorId,
            );
        }
    }

    public function recordCancellation(CancelOrderFinanceData $data): void
    {
        $this->ledger->lockCustomer($data->customerId);
        $original = \Modules\Finance\Models\LedgerEntry::query()
            ->where('event_key', 'order-confirm:'.$data->orderId)
            ->where('customer_id', $data->customerId)
            ->where('order_id', $data->orderId)
            ->where('type', LedgerEntryType::ORDER_DEBT->value)
            ->first();

        if ($original === null) {
            throw new FinanceException('missing_order_liability');
        }

        // Reverse exactly the posted liability, not a possibly different DTO total.
        $this->ledger->post($data->customerId, LedgerEntryType::ORDER_CANCELLATION,
            -Money::cents((string) $original->signed_amount), 'order-cancel:'.$data->orderId,
            orderId: $data->orderId, reversesId: $original->id);
    }
}
