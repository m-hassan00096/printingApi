<?php
namespace Modules\Production\Http\Requests;

use Illuminate\Validation\Validator;
use Modules\Settings\Contracts\PrintingLocationLookup;

class PrintingLocationRequest extends ProductionWriteRequest
{
    public function rules(): array
    {
        return ['printingLocationId' => ['required', 'integer', 'min:1']];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if (count(array_diff(array_keys($this->all()), ['printingLocationId'])) > 0) {
                $validator->errors()->add('body', __('production.messages.unexpected_fields'));
            }
            if (! $validator->errors()->has('printingLocationId') && $this->filled('printingLocationId')
                && ! app(PrintingLocationLookup::class)->belongsToPrintingLocations((int) $this->input('printingLocationId'))) {
                $validator->errors()->add('printingLocationId',
                    __('validation.exists', ['attribute' => 'printingLocationId']));
            }
        }];
    }
}
