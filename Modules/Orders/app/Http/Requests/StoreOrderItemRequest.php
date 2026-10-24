<?php
namespace Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Orders\Http\Requests\Concerns\RejectsUnknownFields;

class StoreOrderItemRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function allowedPayloadFields(): array
    {
        return ['serviceId', 'quantity', 'specifications', 'agreedTotal'];
    }

    public function rules(): array
    {
        return [
            'serviceId' => [
                'required', 'integer',
                Rule::exists('printing_services', 'id')->whereNull('deleted_at')->where('status', 1),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'specifications' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'agreedTotal' => [
                'required', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D',
            ],
        ];
    }
}
