<?php
namespace Modules\Orders\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Orders\Enums\OrderSource;
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
        return ['customerId', 'source', 'dueDate', 'notes'];
    }

    public function rules(): array
    {
        return [
            'customerId' => [
                'sometimes', 'required', 'integer',
                Rule::exists('customers', 'id')->whereNull('deleted_at')->where('status', 1),
            ],
            'source' => ['sometimes', 'required', 'integer', Rule::enum(OrderSource::class)],
            'dueDate' => [
                'sometimes', 'nullable', 'date_format:Y-m-d',
                'after_or_equal:'.CarbonImmutable::now('Africa/Cairo')->toDateString(),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
