<?php
namespace Modules\Settings\Services;

use Modules\Settings\Contracts\PaymentDestinationLookup;
use Modules\Settings\Enums\PaymentDestinationStatus;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Models\EWallet;

class PaymentDestinationResolver implements PaymentDestinationLookup
{
    public function available(int $method, ?int $destinationId): bool
    {
        if ($method === 0) {
            return $destinationId === null;
        }
        if ($destinationId === null) {
            return false;
        }
        return match ($method) {
            1 => BankAccount::query()->whereKey($destinationId)
                ->where('status', PaymentDestinationStatus::ACTIVE->value)->exists(),
            2 => EWallet::query()->whereKey($destinationId)
                ->where('status', PaymentDestinationStatus::ACTIVE->value)->exists(),
            default => false,
        };
    }

    public function resolve(int $method, ?int $destinationId): ?array
    {
        if ($method === 0 && $destinationId === null) {
            return ['bankAccountId' => null, 'eWalletId' => null, 'label' => null];
        }
        if ($destinationId !== null && $method === 1) {
            $bank = BankAccount::query()->whereKey($destinationId)
                ->where('status', PaymentDestinationStatus::ACTIVE->value)->lockForUpdate()->first();
            if ($bank !== null) {
                return ['bankAccountId' => $bank->id, 'eWalletId' => null, 'label' => $bank->bank_name];
            }
        }
        if ($destinationId !== null && $method === 2) {
            $wallet = EWallet::query()->whereKey($destinationId)
                ->where('status', PaymentDestinationStatus::ACTIVE->value)->lockForUpdate()->first();
            if ($wallet !== null) {
                return ['bankAccountId' => null, 'eWalletId' => $wallet->id, 'label' => $wallet->name];
            }
        }
        return null;
    }
}
