<?php
namespace Modules\Orders\Http\Requests\Concerns;

trait RejectsUnknownFields
{
    abstract protected function allowedPayloadFields(): array;

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, $this->allowedPayloadFields(), true)) {
                    $validator->errors()->add(
                        $field, __('orders.messages.unsupported_field', ['field' => $field]),
                    );
                }
            }
        });
    }
}
