<?php

namespace Modules\Settings\Services\Selects;

use App\Exceptions\Select\InvalidSelectRequestException;
use Modules\Settings\Enums\PaymentDestinationStatus;
use Modules\Settings\Models\BankAccount;
use Modules\Settings\Models\EWallet;

class PaymentDestinationSelectService
{
    /**
     * Only active, non-deleted bank accounts can be selected for new payments.
     *
     * @return array<int, array{value: int, label: string, isDefault: int}>
     */
    public function getBankAccounts(?string $filter = null): array
    {
        $this->validateFilter($filter);

        return BankAccount::query()
            ->where('status', PaymentDestinationStatus::ACTIVE->value)
            ->orderBy('bank_name')
            ->orderBy('id')
            ->get(['id', 'bank_name', 'account_number', 'is_default'])
            ->map(static fn (BankAccount $account): array => [
                'value' => $account->id,
                'label' => $account->bank_name.' - '.self::maskedIdentifier($account->account_number),
                'isDefault' => $account->is_default->value,
            ])
            ->all();
    }

    /**
     * Only active, non-deleted wallets can be selected for new payments.
     *
     * @return array<int, array{value: int, label: string, isDefault: int}>
     */
    public function getEWallets(?string $filter = null): array
    {
        $this->validateFilter($filter);

        return EWallet::query()
            ->where('status', PaymentDestinationStatus::ACTIVE->value)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'phone', 'is_default'])
            ->map(static fn (EWallet $wallet): array => [
                'value' => $wallet->id,
                'label' => $wallet->name.' - '.self::maskedIdentifier($wallet->phone),
                'isDefault' => $wallet->is_default->value,
            ])
            ->all();
    }

    private function validateFilter(?string $filter): void
    {
        // Match the active-only convention used by existing Identity and Settings selects.
        if ($filter !== null && $filter !== 'active') {
            throw new InvalidSelectRequestException();
        }
    }

    private static function maskedIdentifier(string $identifier): string
    {
        // Never reveal the full identifier, even for unusually short bank numbers.
        return '****'.(strlen($identifier) > 4 ? substr($identifier, -4) : '');
    }
}
