<?php
namespace Modules\Customers\Http\Requests\Concerns;

use Closure;
use Modules\Customers\Models\Customer;
use Modules\Customers\Support\CustomerPhone;
use Modules\Settings\Enums\ParameterValueStatus;
use Modules\Settings\Models\Parameter;
use Illuminate\Validation\Rule;

trait ValidatesCustomerFields
{
    private function customerTypeRule(): mixed
    {
        return Rule::exists('parameter_values', 'id')
            ->where('parameter_id', Parameter::query()->where('name', 'customer_types')->value('id'))
            ->where('status', ParameterValueStatus::ACTIVE->value)
            ->whereNull('deleted_at');
    }

    private function uniquePhoneRule(?int $ignoreCustomerId = null): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($ignoreCustomerId): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            $query = Customer::query()
                ->where('live_phone_hash', CustomerPhone::hash($value));

            if ($ignoreCustomerId !== null) {
                $query->whereKeyNot($ignoreCustomerId);
            }

            if ($query->exists()) {
                $fail(__('customers.messages.duplicate_phone'));
            }
        };
    }
}
