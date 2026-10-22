<?php

namespace Modules\Settings\Actions\EWallets;

use Modules\Settings\Enums\DefaultStatus;
use Modules\Settings\Models\EWallet;
use Modules\Settings\Services\PaymentDestinationDefaultService;

class UpdateEWalletAction
{
    public function execute(EWallet $eWallet, array $data, PaymentDestinationDefaultService $defaults): EWallet
    {
        $attributes = [];

        foreach ([
            'name' => 'name',
            'phone' => 'phone',
            'accountHolder' => 'account_holder',
            'paymentLink' => 'payment_link',
            'status' => 'status',
        ] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $attributes[$column] = $data[$input];
            }
        }

        return $defaults->update(
            $eWallet,
            'e_wallets',
            $attributes,
            array_key_exists('isDefault', $data) ? DefaultStatus::from((int) $data['isDefault']) : null,
        );
    }
}
