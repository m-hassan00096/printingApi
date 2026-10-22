<?php

namespace Modules\Settings\Actions\EWallets;

use Modules\Settings\Models\EWallet;
use Modules\Settings\Services\PaymentDestinationDefaultService;

class DeleteEWalletAction
{
    public function execute(EWallet $eWallet, PaymentDestinationDefaultService $defaults): void
    {
        $defaults->delete($eWallet, 'e_wallets');
    }
}
