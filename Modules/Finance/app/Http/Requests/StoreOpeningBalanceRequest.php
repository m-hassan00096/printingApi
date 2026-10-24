<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Enums\BalanceType;

class StoreOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D', 'not_in:0,0.0,0.00'],
            'type' => ['required', 'integer', Rule::in([BalanceType::DEBIT->value, BalanceType::CREDIT->value])],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
