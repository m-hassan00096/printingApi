<?php
namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Customers\Contracts\CustomerFinanceReference;
use Modules\Finance\Enums\LedgerEntryType;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\Refund;
use Modules\Finance\Support\Money;

class RefundService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly PaymentDetailsService $payments,
        private readonly CustomerFinanceReference $customers,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function create(array $data, int $actorId, string $keyHash): Refund
    {
        return DB::transaction(function () use ($data, $actorId, $keyHash): Refund {
            $claim = $this->idempotency->claim($actorId, 'refund_create', $keyHash, $data);
            if ($claim->result_id !== null) {
                return Refund::query()->findOrFail($claim->result_id);
            }
            $customerId = (int) $data['customerId'];
            $amount = Money::positive($data['amount']);
            $this->ledger->lockCustomer($customerId);
            $balance = $this->ledger->cents($customerId);
            if ($balance >= 0 || -$balance < $amount) {
                throw new FinanceException('insufficient_credit');
            }
            $destination = $this->payments->resolve((int) $data['paymentMethod'], $data['paymentDestinationId'] ?? null);
            $display = $this->customers->display($customerId);
            $refund = Refund::query()->create([
                'refund_number' => 'pending-'.Str::uuid(),
                'customer_id' => $customerId,
                'customer_name_snapshot' => $display['name'], 'customer_phone_snapshot' => $display['phone'],
                'amount' => Money::format($amount), 'payment_method' => (int) $data['paymentMethod'],
                'bank_account_id' => $destination['bankAccountId'], 'e_wallet_id' => $destination['eWalletId'],
                'destination_snapshot' => $destination['label'],
                'refunded_on' => now('Africa/Cairo')->toDateString(),
                'reason' => $data['reason'], 'created_by' => $actorId,
            ]);
            $refund->refund_number = 'REF-'.now('Africa/Cairo')->format('Y').'-'.str_pad((string) $refund->id, 5, '0', STR_PAD_LEFT);
            $refund->save();
            $this->ledger->post($customerId, LedgerEntryType::REFUND, $amount,
                'refund:'.$refund->id, refundId: $refund->id, note: $refund->reason);
            $this->idempotency->finish($claim, 'refund', $refund->id);
            return $refund;
        }, 3);
    }
}
