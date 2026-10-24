<?php
namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Modules\Finance\Enums\BalanceType;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Support\Money;

class LedgerService
{
    /** Lock the customer record first for every financial write, consistent with Customers/Orders. */
    public function lockCustomer(int $customerId): void
    {
        if (! DB::table('customers')->where('id', $customerId)->whereNull('deleted_at')->lockForUpdate()->exists()) {
            throw new FinanceException('customer_not_found', \App\Enums\HttpStatusCode::NOT_FOUND);
        }
    }

    public function cents(int $customerId): int
    {
        return Money::cents((string) (DB::table('finance_ledger_entries')
            ->where('customer_id', $customerId)->sum('signed_amount') ?: '0'));
    }

    public function balance(int $customerId): array
    {
        $cents = $this->cents($customerId);
        $type = $cents > 0 ? BalanceType::DEBIT : ($cents < 0 ? BalanceType::CREDIT : BalanceType::ZERO);
        return ['amount' => Money::format(abs($cents)), 'type' => $type->value, 'typeLabel' => $type->label()];
    }

    public function post(int $customerId, LedgerEntryType $type, int $signedCents, string $eventKey, ?int $orderId = null, ?int $collectionId = null, ?int $refundId = null, ?int $reversesId = null, ?string $effectiveDate = null, ?string $note = null): LedgerEntry
    {
        if (abs($signedCents) > 999999999999) {
            throw new FinanceException('amount_out_of_range', \App\Enums\HttpStatusCode::UNPROCESSABLE_ENTITY);
        }
        if (DB::table('finance_ledger_entries')->where('event_key', $eventKey)->exists()) {
            throw new FinanceException('duplicate_operation');
        }
        return LedgerEntry::query()->create([
            'customer_id' => $customerId, 'type' => $type->value,
            'signed_amount' => Money::format($signedCents), 'event_key' => $eventKey,
            'order_id' => $orderId, 'collection_id' => $collectionId, 'refund_id' => $refundId,
            'reverses_entry_id' => $reversesId, 'effective_date' => $effectiveDate ?? now('Africa/Cairo')->toDateString(),
            'note' => $note, 'created_by' => Auth::id(), 'created_at' => now(),
        ]);
    }
}
