<?php

namespace Modules\Settings\Actions\BankAccounts;

use Modules\Settings\Models\BankAccount;
use Modules\Settings\Services\PaymentDestinationDefaultService;

class CreateBankAccountAction
{
    public function execute(array $data, PaymentDestinationDefaultService $defaults): BankAccount
    {
        return $defaults->create(
            BankAccount::class,
            'bank_accounts',
            [
                'bank_name' => $data['bankName'],
                'account_number' => $data['accountNumber'],
                'status' => $data['status'],
            ],
            (bool) ($data['isDefault'] ?? false),
        );
    }
}
