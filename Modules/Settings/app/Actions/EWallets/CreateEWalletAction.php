<?php

namespace Modules\Settings\Actions\EWallets;

use Modules\Settings\Enums\DefaultStatus;
use Modules\Settings\Models\EWallet;
use Modules\Settings\Services\PaymentDestinationDefaultService;

class CreateEWalletAction
{
    public function execute(array $data, PaymentDestinationDefaultService $defaults): EWallet
    {
        return $defaults->create(
            EWallet::class,
            'e_wallets',
            [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'account_holder' => $data['accountHolder'],
                'payment_link' => $data['paymentLink'] ?? null,
                'status' => $data['status'],
            ],
            DefaultStatus::from((int) ($data['isDefault'] ?? DefaultStatus::NOT_DEFAULT->value)),
        );
    }
}
