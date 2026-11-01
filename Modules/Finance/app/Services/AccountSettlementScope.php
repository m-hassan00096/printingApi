<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Support\Money;
use Modules\Orders\Support\OrderMoney;

/**
 * A cumulative CUSTOMER-account scope, not invoice-by-invoice payment allocation.
 * Work is limited by delivery date; actual customer funds are read through today.
 * Snapshots never become charges or payments in the ledger.
 */
final class AccountSettlementScope
{
    public function __construct(private readonly CustomerFinanceReference $customers) {}

    public function build(int $customerId, string $scopeTo, bool $lock = false): array
    {
        $customer = $this->customers->display($customerId);
        $today = now('Africa/Cairo')->toDateString();
        $ordersQuery = DB::table('orders')->where('customer_id', $customerId)->orderBy('id');
        if ($lock) $ordersQuery->lockForUpdate();
        $orders = $ordersQuery->get(['id', 'status', 'order_number'])->keyBy('id');
        $deliveryQuery = DB::table('deliveries')->whereIn('order_id', $orders->keys()->all())
            ->whereNull('cancelled_at')->where('delivered_local_date', '<=', $scopeTo)->orderBy('id');
        if ($lock) $deliveryQuery->lockForUpdate();
        $deliveries = $deliveryQuery->get(['id', 'order_id', 'delivered_local_date', 'cancelled_at'])
            ->filter(fn (object $row): bool => (int) $orders->get($row->order_id)?->status === 1)
            ->unique('order_id')->values();
        $coveredIds = $deliveries->pluck('order_id')->map(fn ($id): int => (int) $id)->all();
        $deliveryByOrder = $deliveries->keyBy('order_id');
        $itemsQuery = DB::table('order_items')->whereIn('order_id', $coveredIds)
            ->whereNull('deleted_at')->orderBy('id');
        if ($lock) $itemsQuery->lockForUpdate();
        $items = $itemsQuery->get(['id', 'order_id', 'service_name_snapshot', 'specifications',
            'quantity', 'agreed_total', 'pricing_method', 'width_cm', 'height_cm']);
        $ledgerQuery = DB::table('finance_ledger_entries')->where('customer_id', $customerId)
            ->where('effective_date', '<=', $today)->orderBy('id');
        if ($lock) $ledgerQuery->lockForUpdate();
        $entries = $ledgerQuery->get(['id', 'type', 'signed_amount', 'order_id', 'collection_id',
            'refund_id', 'reverses_entry_id', 'effective_date', 'note']);
        $receipts = DB::table('finance_collections')
            ->whereIn('id', $entries->pluck('collection_id')->filter()->unique()->all())
            ->pluck('receipt_number', 'id');
        $delivered = 0;
        $lines = [];
        foreach ($items as $item) {
            $cents = Money::cents((string) $item->agreed_total);
            $delivered += $cents;
            $lines[] = [
                'id' => (int) $item->id, 'orderId' => (int) $item->order_id,
                'orderNumber' => $orders->get($item->order_id)->order_number,
                'deliveryDate' => (string) $deliveryByOrder->get($item->order_id)->delivered_local_date,
                'description' => $item->service_name_snapshot, 'specifications' => $item->specifications,
                'quantity' => (int) $item->quantity,
                'unitPrice' => OrderMoney::unitPrice((string) $item->agreed_total, (int) $item->quantity),
                'amount' => Money::format($cents), 'pricingMethod' => $item->pricing_method,
                'widthCm' => $item->width_cm === null ? null : (string) $item->width_cm,
                'heightCm' => $item->height_cm === null ? null : (string) $item->height_cm,
            ];
        }
        $opening = $netFunds = $account = $collected = $reversed = $refunded = $outsideAdvances = 0;
        $payments = [];
        $creditIds = [];
        foreach ($entries as $entry) {
            $cents = Money::cents((string) $entry->signed_amount);
            $account += $cents;
            $type = LedgerEntryType::from((int) $entry->type);
            if ($type === LedgerEntryType::OPENING_BALANCE) {
                $opening += $cents;
            }
            if (! in_array($type, [LedgerEntryType::COLLECTION, LedgerEntryType::COLLECTION_REVERSAL, LedgerEntryType::REFUND], true)) continue;
            $netFunds -= $cents;
            if ($type === LedgerEntryType::COLLECTION) {
                $collected -= $cents;
                $creditIds[] = (int) $entry->id;
            } elseif ($type === LedgerEntryType::COLLECTION_REVERSAL) {
                $reversed += $cents;
            } else {
                $refunded += $cents;
            }
            $outside = $entry->order_id !== null && ! in_array((int) $entry->order_id, $coveredIds, true)
                && (int) $orders->get($entry->order_id)?->status === 1;
            if ($outside && $type !== LedgerEntryType::REFUND) $outsideAdvances -= $cents;
            $payments[] = [
                'entryId' => (int) $entry->id, 'type' => $type->value, 'typeLabel' => $type->label(),
                'effectiveDate' => (string) $entry->effective_date, 'signedAmount' => Money::format($cents),
                'collectionId' => $entry->collection_id === null ? null : (int) $entry->collection_id,
                'receiptNumber' => $receipts->get($entry->collection_id),
                'orderId' => $entry->order_id === null ? null : (int) $entry->order_id,
                'outsideWorkScope' => $outside,
            ];
        }
        $balance = $opening + $delivered - $netFunds;
        $manifest = [
            'ledgerMaxId' => (int) ($entries->max('id') ?? 0),
            'creditEntryIds' => $creditIds,
            'deliveryIds' => $deliveries->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'orderIds' => $coveredIds,
        ];
        // Include identities, not just totals: equal-amount reversals/replacements
        // invalidate an already displayed preview as well.
        $hash = hash('sha256', json_encode([
            $customerId, $scopeTo, $today,
            $orders->values()->all(), $deliveries->all(), $items->all(), $entries->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return [
            'customerId' => $customerId, 'customerName' => $customer['name'],
            'customerPhone' => $customer['phone'], 'dateTo' => $scopeTo, 'paymentsThrough' => $today,
            'scope' => 'cumulative_delivered_work', 'previewHash' => $hash,
            'openingBalance' => Money::format($opening), 'deliveredTotal' => Money::format($delivered),
            'grossCollections' => Money::format($collected), 'collectionReversals' => Money::format($reversed),
            'refundsTotal' => Money::format($refunded), 'netPayments' => Money::format($netFunds),
            'advancesForOtherWork' => Money::format($outsideAdvances),
            'balanceBefore' => Money::format($balance), 'dueNow' => Money::format(max(0, $balance)),
            'creditInScope' => Money::format(max(0, -$balance)),
            'accountBalance' => Money::format($account), 'outsideBalance' => Money::format($account - $balance),
            'hasWork' => $items->isNotEmpty() || $opening !== 0,
            'items' => $lines, 'payments' => $payments, 'sourceManifest' => $manifest,
        ];
    }
}
