<?php

namespace Modules\Finance\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Support\Money;
use Modules\Orders\Support\OrderMoney;

/** Historical delivered-work statement only. Creating it never posts money. */
class CustomerSettlementService
{
    public function __construct(private readonly CustomerFinanceReference $customers) {}

    public function statement(int $customerId, string $from, string $to): array
    {
        $customer = $this->customers->display($customerId);
        $events = [];
        $deliveries = DB::table('deliveries as d')->join('orders as o', 'o.id', '=', 'd.order_id')
            ->where('o.customer_id', $customerId)->where('d.delivered_local_date', '<=', $to)
            ->orderBy('d.delivered_local_date')->orderBy('d.id')
            ->get(['d.id', 'd.order_id', 'd.delivered_local_date', 'd.cancelled_at', 'o.order_number']);
        $items = DB::table('order_items')->whereIn('order_id', $deliveries->pluck('order_id')->unique()->all())
            ->whereNull('deleted_at')->orderBy('id')
            ->get(['id', 'order_id', 'service_name_snapshot', 'specifications', 'quantity', 'agreed_total',
                'pricing_method', 'width_cm', 'height_cm'])->groupBy('order_id');

        foreach ($deliveries as $delivery) {
            $orderCents = 0;
            foreach ($items->get($delivery->order_id, collect()) as $item) {
                $amount = Money::cents((string) $item->agreed_total);
                $orderCents += $amount;
                $quantity = (int) $item->quantity;
                $name = (string) $item->service_name_snapshot;
                if ($item->width_cm !== null && $item->height_cm !== null) {
                    $name .= ' — '.$this->dimension($item->width_cm).' × '.$this->dimension($item->height_cm).' '.__('settlements.report.cm');
                }
                if ($item->specifications !== null && trim((string) $item->specifications) !== '') {
                    $name .= ' — '.trim((string) $item->specifications);
                }
                $events[] = [
                    'key' => 'delivery:'.$delivery->id.':item:'.$item->id, 'kind' => 'delivered_item',
                    'date' => (string) $delivery->delivered_local_date, 'description' => $name,
                    'orderId' => (int) $delivery->order_id, 'orderNumber' => $delivery->order_number,
                    'receiptNumber' => null, 'quantity' => $quantity,
                    // The agreed total is authoritative. This is per-piece, not a square-unit rate.
                    'unitPrice' => $quantity > 0 ? OrderMoney::unitPrice((string) $item->agreed_total, $quantity) : null,
                    'cents' => $amount, 'sort' => sprintf('1:%012d:%012d', $delivery->id, $item->id),
                ];
            }
            if ($delivery->cancelled_at !== null) {
                $date = CarbonImmutable::parse((string) $delivery->cancelled_at, 'UTC')->setTimezone('Africa/Cairo')->toDateString();
                if ($date <= $to) {
                    $events[] = [
                        'key' => 'delivery-cancel:'.$delivery->id, 'kind' => 'delivery_cancellation',
                        'date' => $date, 'description' => __('settlements.report.delivery_cancellation'),
                        'orderId' => (int) $delivery->order_id, 'orderNumber' => $delivery->order_number,
                        'receiptNumber' => null, 'quantity' => null, 'unitPrice' => null,
                        'cents' => -$orderCents, 'sort' => sprintf('2:%012d', $delivery->id),
                    ];
                }
            }
        }

        // Read actual immutable cash entries, not receipt status or settlement snapshots.
        $types = [LedgerEntryType::COLLECTION->value, LedgerEntryType::COLLECTION_REVERSAL->value,
            LedgerEntryType::REFUND->value, LedgerEntryType::OPENING_BALANCE->value,
            LedgerEntryType::SETTLEMENT_DISCOUNT->value];
        $movements = DB::table('finance_ledger_entries as l')
            ->leftJoin('finance_collections as c', 'c.id', '=', 'l.collection_id')
            ->leftJoin('finance_refunds as r', 'r.id', '=', 'l.refund_id')
            ->where('l.customer_id', $customerId)->whereIn('l.type', $types)->where('l.effective_date', '<=', $to)
            ->orderBy('l.effective_date')->orderBy('l.id')
            ->get(['l.id', 'l.type', 'l.signed_amount', 'l.effective_date', 'l.note', 'l.order_id',
                'c.receipt_number', 'c.actual_receipt_number', 'r.refund_number']);
        foreach ($movements as $movement) {
            $type = LedgerEntryType::from((int) $movement->type);
            $kind = match ($type) {
                LedgerEntryType::COLLECTION => 'collection', LedgerEntryType::COLLECTION_REVERSAL => 'collection_reversal',
                LedgerEntryType::REFUND => 'refund', LedgerEntryType::OPENING_BALANCE => 'opening_balance',
                LedgerEntryType::SETTLEMENT_DISCOUNT => 'settlement_discount',
                default => throw new \LogicException('Unexpected settlement movement'),
            };
            $events[] = [
                'key' => 'finance:'.$movement->id, 'kind' => $kind, 'date' => (string) $movement->effective_date,
                'description' => __('settlements.report.'.$kind).($movement->note ? ' — '.$movement->note : ''),
                'orderId' => $movement->order_id === null ? null : (int) $movement->order_id, 'orderNumber' => null,
                'receiptNumber' => $movement->receipt_number ?? $movement->refund_number,
                'actualReceiptNumber' => $movement->actual_receipt_number, 'quantity' => null, 'unitPrice' => null,
                'cents' => Money::cents((string) $movement->signed_amount), 'sort' => sprintf('3:%012d', $movement->id),
            ];
        }
        usort($events, static fn (array $a, array $b): int => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);
        $opening = $deliveredTotal = $paymentsTotal = $adjustmentsTotal = $discountsTotal = $balance = 0;
        $lines = [];
        foreach ($events as $event) {
            $cents = $event['cents'];
            if ($event['date'] < $from) {$opening += $cents; $balance += $cents; continue;}
            if (in_array($event['kind'], ['delivered_item', 'delivery_cancellation'], true)) $deliveredTotal += $cents;
            elseif ($event['kind'] === 'opening_balance') $adjustmentsTotal += $cents;
            elseif ($event['kind'] === 'settlement_discount') $discountsTotal -= $cents;
            else $paymentsTotal -= $cents;
            $balance += $cents;
            unset($event['sort'], $event['cents']);
            $event['signedAmount'] = Money::format($cents);
            $event['runningBalance'] = Money::format($balance);
            $lines[] = $event;
        }
        $financeBalance = Money::cents((string) DB::table('finance_ledger_entries')->where('customer_id', $customerId)
            ->where('effective_date', '<=', $to)->selectRaw('COALESCE(SUM(signed_amount), 0) AS amount')->value('amount'));
        return [
            'customerId' => $customerId, 'customerName' => $customer['name'], 'customerPhone' => $customer['phone'],
            'dateFrom' => $from, 'dateTo' => $to, 'openingBalance' => Money::format($opening),
            'deliveredTotal' => Money::format($deliveredTotal), 'paymentsTotal' => Money::format($paymentsTotal),
            'discountsTotal' => Money::format($discountsTotal),
            'adjustmentsTotal' => Money::format($adjustmentsTotal), 'closingBalance' => Money::format($balance),
            'financeBalanceAsOf' => Money::format($financeBalance),
            'undeliveredDifference' => Money::format($financeBalance - $balance), 'lines' => $lines,
        ];
    }

    private function dimension(mixed $value): string
    {
        $text = (string) $value;
        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
