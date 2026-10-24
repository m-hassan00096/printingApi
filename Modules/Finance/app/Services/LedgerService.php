<?php
namespace Modules\Finance\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Finance\Enums\BalanceType;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Support\Money;

class LedgerService
{
    public function __construct(private readonly CustomerFinanceReference $customers) {}

    /** Every financial writer acquires the same customer lock before ledger posts. */
    public function lockCustomer(int $customerId): void
    {
        $this->customers->assertExistsAndLock($customerId);
    }

    public function cents(int $customerId): int
    {
        $value = DB::table('finance_ledger_entries')->where('customer_id', $customerId)
            ->selectRaw('COALESCE(SUM(signed_amount), 0) AS amount')->value('amount');
        return Money::cents((string) $value);
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
