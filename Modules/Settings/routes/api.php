<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\BankAccountController;
use Modules\Settings\Http\Controllers\EWalletController;
use Modules\Settings\Http\Controllers\ParameterValueController;
use Modules\Settings\Http\Controllers\ParameterGroupController;

Route::middleware('auth:sanctum')
    ->prefix('v1/settings')
    ->group(function (): void {
        Route::get('parameter-groups', ParameterGroupController::class)
            ->middleware('permission:settings.view_parameter_values');

        Route::get('parameter-values', [ParameterValueController::class, 'index'])
            ->middleware('permission:settings.view_parameter_values');

        Route::get('parameter-values/{parameterValue}', [ParameterValueController::class, 'show'])
            ->middleware('permission:settings.view_parameter_values');

        Route::post('parameter-values', [ParameterValueController::class, 'store'])
            ->middleware('permission:settings.create_parameter_value');

        Route::match(['put', 'patch'], 'parameter-values/{parameterValue}', [ParameterValueController::class, 'update'])
            ->middleware('permission:settings.update_parameter_value');

        Route::delete('parameter-values/{parameterValue}', [ParameterValueController::class, 'destroy'])
            ->middleware('permission:settings.delete_parameter_value');

        Route::get('bank-accounts', [BankAccountController::class, 'index'])
            ->middleware('permission:settings.bank_accounts.view');
        Route::get('bank-accounts/{bankAccount}', [BankAccountController::class, 'show'])
            ->middleware('permission:settings.bank_accounts.view');
        Route::post('bank-accounts', [BankAccountController::class, 'store'])
            ->middleware('permission:settings.bank_accounts.create');
        Route::match(['put', 'patch'], 'bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])
            ->middleware('permission:settings.bank_accounts.update');
        Route::delete('bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy'])
            ->middleware('permission:settings.bank_accounts.delete');

        Route::get('e-wallets', [EWalletController::class, 'index'])
            ->middleware('permission:settings.e_wallets.view');
        Route::get('e-wallets/{eWallet}', [EWalletController::class, 'show'])
            ->middleware('permission:settings.e_wallets.view');
        Route::post('e-wallets', [EWalletController::class, 'store'])
            ->middleware('permission:settings.e_wallets.create');
        Route::match(['put', 'patch'], 'e-wallets/{eWallet}', [EWalletController::class, 'update'])
            ->middleware('permission:settings.e_wallets.update');
        Route::delete('e-wallets/{eWallet}', [EWalletController::class, 'destroy'])
            ->middleware('permission:settings.e_wallets.delete');
    });
