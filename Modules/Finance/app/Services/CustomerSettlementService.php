<?php

namespace Modules\Finance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Support\Money;
use Modules\Orders\Support\OrderMoney;

/**
 * Read-only settlement of work physically delivered to a customer.
 *
 * This is NOT the financial ledger: confirmed-but-undelivered orders remain
 * debts in Finance, but appear here only after an actual delivery.
 * No rows are posted or changed by generating a statement.
 */
class CustomerSettlementService
{
    public function __construct(private readonly CustomerFinanceReference $customers) {}

    public function statement(int $customerId, string $from, string $to): array
    {
        $customer = $this->customers->display($customerId);
        $events = [];

        // Read ALL delivery events up to the closing date, including cancellations.
        // This lets previous deliveries roll into the opening balance correctly.
        $deliveries = DB::table('deliveries as d')
            ->join('orders as o', 'o.id', '=', 'd.order_id')
            ->where('o.customer_id', $customerId)
            ->where('d.delivered_local_date', '<=', $to)
            ->orderBy('d.delivered_local_date')->orderBy('d.id')
            ->get([
                'd.id', 'd.order_id', 'd.delivered_local_date', 'd.cancelled_at',
                'o.order_number',
            ]);

        $items = DB::table('order_items')
            ->whereIn('order_id', $deliveries->pluck('order_id')->unique()->all())
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'order_id', 'service_name_snapshot', 'specifications',
                'quantity', 'agreed_total'])
            ->groupBy('order_id');

        foreach ($deliveries as $delivery) {
            $orderCents = 0;
            foreach ($items->get($delivery->order_id, collect()) as $item) {
                $amount = Money::cents((string) $item->agreed_total);
                $orderCents += $amount;
                $quantity = (int) $item->quantity;
                $name = (string) $item->service_name_snapshot;
                if ($item->specifications !== null && trim((string) $item->specifications) !== '') {
                    $name .= ' — '.trim((string) $item->specifications);
                }
                $events[] = [
                    'key' => 'delivery:'.$delivery->id.':item:'.$item->id,
                    'kind' => 'delivered_item',
                    'date' => (string) $delivery->delivered_local_date,
                    'description' => $name,
                    'orderId' => (int) $delivery->order_id,
                    'orderNumber' => $delivery->order_number,
                    'receiptNumber' => null,
                    'quantity' => $quantity,
                    // Informational unit price only; agreed_total is authoritative.
                    'unitPrice' => $quantity > 0
                        ? OrderMoney::unitPrice((string) $item->agreed_total, $quantity)
                        : null,
                    'cents' => $amount,
                    'sort' => sprintf('1:%012d:%012d', $delivery->id, $item->id),
                ];
            }
            // A cancelled delivery reverses the delivered-work report on the
            // actual cancellation date. It does not alter the Finance ledger.
            if ($delivery->cancelled_at !== null) {
                $cancellationDate = CarbonImmutable::parse((string) $delivery->cancelled_at, 'UTC')
                    ->setTimezone('Africa/Cairo')->toDateString();
                if ($cancellationDate <= $to) {
                    $events[] = [
                        'key' => 'delivery-cancel:'.$delivery->id,
                        'kind' => 'delivery_cancellation',
                        'date' => $cancellationDate,
                        'description' => 'إلغاء تسليم الأوردر',
                        'orderId' => (int) $delivery->order_id,
                        'orderNumber' => $delivery->order_number,
                        'receiptNumber' => null,
                        'quantity' => null,
                        'unitPrice' => null,
                        'cents' => -$orderCents,
                        'sort' => sprintf('2:%012d', $delivery->id),
                    ];
                }
            }
        }

        // Use immutable Finance entries rather than receipt status: this keeps
        // payments, backdated collections, reversals and actual refunds intact.
        // ORDER_DEBT and ORDER_CANCELLATION are deliberately excluded.
        $financialTypes = [
            LedgerEntryType::COLLECTION->value,
            LedgerEntryType::COLLECTION_REVERSAL->value,
            LedgerEntryType::REFUND->value,
            LedgerEntryType::OPENING_BALANCE->value,
        ];
        $movements = DB::table('finance_ledger_entries as l')
            ->leftJoin('finance_collections as c', 'c.id', '=', 'l.collection_id')
            ->leftJoin('finance_refunds as r', 'r.id', '=', 'l.refund_id')
            ->where('l.customer_id', $customerId)
            ->whereIn('l.type', $financialTypes)
            ->where('l.effective_date', '<=', $to)
            ->orderBy('l.effective_date')->orderBy('l.id')
            ->get([
                'l.id', 'l.type', 'l.signed_amount', 'l.effective_date', 'l.note',
                'l.order_id', 'c.receipt_number', 'c.actual_receipt_number',
                'r.refund_number',
            ]);

        foreach ($movements as $movement) {
            $type = LedgerEntryType::from((int) $movement->type);
            $kind = match ($type) {
                LedgerEntryType::COLLECTION => 'collection',
                LedgerEntryType::COLLECTION_REVERSAL => 'collection_reversal',
                LedgerEntryType::REFUND => 'refund',
                LedgerEntryType::OPENING_BALANCE => 'opening_balance',
                default => throw new \LogicException('Unexpected settlement movement'),
            };
            $label = match ($type) {
                LedgerEntryType::COLLECTION => 'تحصيل من العميل',
                LedgerEntryType::COLLECTION_REVERSAL => 'عكس تحصيل',
                LedgerEntryType::REFUND => 'رد مبلغ للعميل',
                LedgerEntryType::OPENING_BALANCE => 'رصيد افتتاحي',
                default => '',
            };
            $events[] = [
                'key' => 'finance:'.$movement->id,
                'kind' => $kind,
                'date' => (string) $movement->effective_date,
                'description' => $label.($movement->note ? ' — '.$movement->note : ''),
                'orderId' => $movement->order_id === null ? null : (int) $movement->order_id,
                'orderNumber' => null,
                'receiptNumber' => $movement->receipt_number ?? $movement->refund_number,
                'actualReceiptNumber' => $movement->actual_receipt_number,
                'quantity' => null,
                'unitPrice' => null,
                'cents' => Money::cents((string) $movement->signed_amount),
                'sort' => sprintf('3:%012d', $movement->id),
            ];
        }

        usort($events, static fn (array $a, array $b): int =>
            [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);

        $opening = 0;
        $deliveredTotal = 0;
        $paymentsTotal = 0;
        $adjustmentsTotal = 0;
        $balance = 0;
        $lines = [];

        foreach ($events as $event) {
            $cents = $event['cents'];
            if ($event['date'] < $from) {
                $opening += $cents;
                $balance += $cents;
                continue;
            }
            if (in_array($event['kind'], ['delivered_item', 'delivery_cancellation'], true)) {
                $deliveredTotal += $cents;
            } elseif ($event['kind'] === 'opening_balance') {
                $adjustmentsTotal += $cents;
            } else {
                $paymentsTotal -= $cents;
            }
            $balance += $cents;
            unset($event['sort'], $event['cents']);
            $event['signedAmount'] = Money::format($cents);
            $event['runningBalance'] = Money::format($balance);
            $lines[] = $event;
        }

        $financeBalance = Money::cents((string) DB::table('finance_ledger_entries')
            ->where('customer_id', $customerId)
            ->where('effective_date', '<=', $to)
            ->selectRaw('COALESCE(SUM(signed_amount), 0) AS amount')
            ->value('amount'));

        return [
            'customerId' => $customerId,
            'customerName' => $customer['name'],
            'customerPhone' => $customer['phone'],
            'dateFrom' => $from, 'dateTo' => $to,
            'openingBalance' => Money::format($opening),
            'deliveredTotal' => Money::format($deliveredTotal),
            'paymentsTotal' => Money::format($paymentsTotal),
            'adjustmentsTotal' => Money::format($adjustmentsTotal),
            'closingBalance' => Money::format($balance),
            // Internal-use reconciliation, not a balance to print for customers.
            'financeBalanceAsOf' => Money::format($financeBalance),
            'undeliveredDifference' => Money::format($financeBalance - $balance),
            'lines' => $lines,
        ];
    }
}
