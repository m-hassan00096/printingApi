<?php
namespace Modules\Orders\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Orders\Enums\OrderSource;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Http\Requests\Concerns\RejectsUnknownFields;
use Modules\Orders\Http\Requests\Concerns\ValidatesOrderDeposit;

class UpdateOrderRequest extends FormRequest
{
    use RejectsUnknownFields, ValidatesOrderDeposit;

    public function authorize(): bool { return true; }

    protected function allowedPayloadFields(): array
    {
        return ['customerId', 'source', 'dueDate', 'notes', 'status', 'depositAmount', 'paymentMethod', 'paymentDestinationId'];
    }

    public function rules(): array
    {
        return [
            'customerId' => ['sometimes', 'required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')->where('status', 1)],
            'source' => ['sometimes', 'required', 'integer', Rule::enum(OrderSource::class)],
            'status' => ['sometimes', 'required', 'integer', Rule::in([OrderStatus::DRAFT->value, OrderStatus::CONFIRMED->value])],
            ...$this->depositRules((int) $this->input('status', 0) === OrderStatus::CONFIRMED->value),
            'dueDate' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::now('Africa/Cairo')->toDateString()],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
    public function after(): array
    {
        return $this->depositValidationCallbacks();
    }

}
