<?php
namespace Modules\Orders\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Orders\Enums\OrderSource;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Http\Requests\Concerns\RejectsUnknownFields;

class UpdateOrderRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function allowedPayloadFields(): array
    {
        return ['customerId', 'source', 'dueDate', 'notes', 'status', 'depositAmount'];
    }

    public function rules(): array
    {
        return [
            'customerId' => [
                'sometimes', 'required', 'integer',
                Rule::exists('customers', 'id')->whereNull('deleted_at')->where('status', 1),
            ],
            'source' => ['sometimes', 'required', 'integer', Rule::enum(OrderSource::class)],
            'status' => ['sometimes', 'required', 'integer', Rule::in([
                OrderStatus::DRAFT->value, OrderStatus::CONFIRMED->value,
            ])],
            'depositAmount' => [
                'sometimes', 'nullable', 'string',
                'prohibited_unless:status,1',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D',
            ],
            'dueDate' => [
                'sometimes', 'nullable', 'date_format:Y-m-d',
                'after_or_equal:'.CarbonImmutable::now('Africa/Cairo')->toDateString(),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
