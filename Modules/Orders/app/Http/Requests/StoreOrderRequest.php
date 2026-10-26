<?php
namespace Modules\Orders\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Orders\Http\Requests\Concerns\ValidatesIdempotencyHeader;
use Illuminate\Validation\Rule;
use Modules\Orders\Enums\OrderSource;
use Modules\Orders\Enums\OrderStatus;
use Modules\Orders\Http\Requests\Concerns\RejectsUnknownFields;
use Modules\Orders\Http\Requests\Concerns\ValidatesDraftPaymentPlan;
use Modules\Orders\Http\Requests\Concerns\ValidatesOrderDeposit;

class StoreOrderRequest extends FormRequest
{
    use RejectsUnknownFields, ValidatesOrderDeposit, ValidatesDraftPaymentPlan, ValidatesIdempotencyHeader;

    public function authorize(): bool { return true; }

    protected function allowedPayloadFields(): array
    {
        return ['customerId', 'source', 'dueDate', 'notes', 'items', 'status', 'depositAmount', 'paymentMethod', 'paymentDestinationId', 'plannedDepositAmount', 'plannedPaymentMethod', 'plannedPaymentDestinationId'];
    }

    public function rules(): array
    {
        return [
            'customerId' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')->where('status', 1)],
            'source' => ['required', 'integer', Rule::enum(OrderSource::class)],
            'status' => ['sometimes', 'required', 'integer', Rule::in([OrderStatus::DRAFT->value, OrderStatus::CONFIRMED->value])],
            ...$this->depositRules((int) $this->input('status', 0) === OrderStatus::CONFIRMED->value),
            ...$this->draftPaymentRules((int) $this->input('status', 0) === OrderStatus::CONFIRMED->value),
            'dueDate' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::now('Africa/Cairo')->toDateString()],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:serviceId,quantity,specifications,agreedTotal'],
            'items.*.serviceId' => ['required', 'integer', Rule::exists('printing_services', 'id')->whereNull('deleted_at')->where('status', 1)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.specifications' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'items.*.agreedTotal' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D'],
        ];
    }
    public function after(): array
    {
        return [...$this->depositValidationCallbacks(), ...$this->idempotencyValidationCallbacks(true)];
    }

}
