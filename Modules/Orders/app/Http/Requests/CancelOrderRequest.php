<?php
namespace Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Orders\Http\Requests\Concerns\ValidatesIdempotencyHeader;

class CancelOrderRequest extends FormRequest
{
    use ValidatesIdempotencyHeader;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:3000']];
    }
    public function after(): array
    {
        return $this->idempotencyValidationCallbacks(true);
    }

}
