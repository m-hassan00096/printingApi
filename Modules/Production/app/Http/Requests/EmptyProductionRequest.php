<?php
namespace Modules\Production\Http\Requests;

class EmptyProductionRequest extends ProductionWriteRequest
{
    public function rules(): array { return []; }

    public function after(): array
    {
        return [...parent::after(), function (\Illuminate\Validation\Validator $validator): void {
            if ($this->all() !== []) {
                $validator->errors()->add('body', __('production.messages.unexpected_fields'));
            }
        }];
    }
}
