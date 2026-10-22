<?php

namespace Modules\PrintingServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PrintingServices\Http\Requests\Concerns\LocalizedPrintingServiceValidation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Http\Requests\Concerns\ValidatesServiceName;
use Modules\PrintingServices\Http\Requests\Concerns\ValidatesTierData;
use Modules\PrintingServices\Support\Money;

class StorePrintingServiceRequest extends FormRequest
{
    use ValidatesServiceName, ValidatesTierData;

    protected function prepareForValidation(): void
    {
        $this->prepareServiceName();
    }

    use LocalizedPrintingServiceValidation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueLiveName()],
            'basePrice' => ['sometimes', 'nullable', function ($attr, $value, $fail): void {
                if (! Money::valid($value, true)) {
                    $fail(__('printing_services.messages.base_price_invalid'));
                }
            }],
            'status' => ['required', 'integer', Rule::enum(PrintingServiceStatus::class)],
            'priceTiers' => ['sometimes', 'array'],
            'priceTiers.*' => ['required', 'array:customerTypeId,minQuantity,maxQuantity,price'],
            'priceTiers.*.customerTypeId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'priceTiers.*.minQuantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'priceTiers.*.maxQuantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'priceTiers.*.price' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tiers = $this->input('priceTiers', []);
            if (! is_array($tiers)) {
                return;
            }

            foreach ($tiers as $index => $tier) {
                if (is_array($tier)) {
                    $this->checkTierFields($validator, $tier, $index, false);
                }
            }
        });
    }
}

