<?php
namespace Modules\Finance\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Enums\BalanceType;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Services\LedgerService;
use Modules\Finance\Support\Money;

class CreateOpeningBalanceAction
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function execute(int $customerId, array $data): void
    {
        DB::transaction(function () use ($customerId, $data): void {
            $this->ledger->lockCustomer($customerId);
            if (LedgerEntry::query()->where('event_key', 'opening:'.$customerId)->exists()) {
                throw new FinanceException('opening_exists');
            }

            $cents = Money::positive($data['amount']);
            $this->ledger->post($customerId, LedgerEntryType::OPENING_BALANCE,
                (int) $data['type'] === BalanceType::DEBIT->value ? $cents : -$cents,
                'opening:'.$customerId, note: $data['note'] ?? null);
        }, 3);
    }
}
