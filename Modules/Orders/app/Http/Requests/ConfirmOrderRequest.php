<?php
namespace Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Orders\Http\Requests\Concerns\ValidatesOrderDeposit;

class ConfirmOrderRequest extends FormRequest
{
    use ValidatesOrderDeposit;

    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return $this->depositRules(true);
    }

    public function after(): array
    {
        return $this->depositValidationCallbacks();
    }
}
