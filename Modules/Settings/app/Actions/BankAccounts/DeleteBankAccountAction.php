<?php

namespace Modules\Settings\Actions\BankAccounts;

use Modules\Settings\Models\BankAccount;
use Modules\Settings\Services\PaymentDestinationDefaultService;

class DeleteBankAccountAction
{
    public function execute(BankAccount $bankAccount, PaymentDestinationDefaultService $defaults): void
    {
        $defaults->delete($bankAccount, 'bank_accounts');
    }
}
