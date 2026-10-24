<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Validator;

/** Reversal has no payment destination; only header and reason are HTTP inputs. */
class ReverseCollectionRequest extends FinanceWriteRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:3000']];
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
