<?php
namespace Modules\Customers\Http\Requests\Concerns;

trait LocalizedCustomerValidation
{
    public function attributes(): array
    {
        $fields = [
            'name' => 'name',
            'phone' => 'phone',
            'address' => 'address',
            'customerTypeId' => 'customer_type',
            'status' => 'status',
            'filter' => 'filter',
            'filter.search' => 'search',
            'filter.customerTypeId' => 'customer_type',
            'filter.status' => 'status',
            'sort' => 'sort',
            'page' => 'page',
            'perPage' => 'per_page',
        ];

        $result = [];
        foreach ($fields as $field => $key) {
            $result[$field] = __('customers.attributes.'.$key);
        }

        return $result;
    }

    public function messages(): array
    {
        $rules = ['required', 'string', 'max', 'min', 'integer', 'enum', 'exists', 'array', 'in'];
        $result = [];

        foreach (array_keys($this->attributes()) as $attribute) {
            foreach ($rules as $rule) {
                $result[$attribute.'.'.$rule] = __('customers.validation.'.$rule);
            }
        }

        return $result;
    }

    protected function prepareCustomerFields(): void
    {
        foreach (['name', 'phone'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }
    }
}
