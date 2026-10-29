<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Validator;

/** Only the corrected amount and audit reason are editable; payment source and linkage are inherited. */
class CorrectCollectionRequest extends FinanceWriteRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D', 'not_in:0,0.0,0.00'],
            'reason' => ['required', 'string', 'min:3', 'max:3000'],
            'confirmCreditBalance' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $key = $this->header('Idempotency-Key');
                if (! is_string($key) || strlen($key) < 1 || strlen($key) > 160) {
                    $validator->errors()->add('Idempotency-Key', __('finance.messages.idempotency_required'));
                }
            },
        ];
    }
}
