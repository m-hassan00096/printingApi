<?php

namespace Modules\Settings\Actions\BankAccounts;

use Modules\Settings\Models\BankAccount;
use Modules\Settings\Services\PaymentDestinationDefaultService;

class UpdateBankAccountAction
{
    public function execute(BankAccount $bankAccount, array $data, PaymentDestinationDefaultService $defaults): BankAccount
    {
        $attributes = [];

        foreach (['bankName' => 'bank_name', 'accountNumber' => 'account_number', 'status' => 'status'] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $attributes[$column] = $data[$input];
            }
        }

        return $defaults->update(
            $bankAccount,
            'bank_accounts',
            $attributes,
            array_key_exists('isDefault', $data) ? (bool) $data['isDefault'] : null,
        );
    }
}
