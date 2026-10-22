<?php

namespace Modules\PrintingServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PrintingServices\Http\Requests\Concerns\LocalizedPrintingServiceValidation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\PrintingServices\Enums\PriceTierActionStatus;
use Modules\PrintingServices\Enums\PrintingServiceStatus;
use Modules\PrintingServices\Http\Requests\Concerns\ValidatesServiceName;
use Modules\PrintingServices\Http\Requests\Concerns\ValidatesTierData;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Support\Money;

class UpdatePrintingServiceRequest extends FormRequest
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
        /** @var PrintingService $service */
        $service = $this->route('printingService');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150', $this->uniqueLiveName($service?->id)],
            'basePrice' => ['sometimes', 'nullable', function ($attr, $value, $fail): void {
                if (! Money::valid($value, true)) {
                    $fail(__('printing_services.messages.base_price_invalid'));
                }
            }],
            'status' => ['sometimes', 'required', 'integer', Rule::enum(PrintingServiceStatus::class)],
            'priceTiers' => ['sometimes', 'array'],
            'priceTiers.*' => ['required', 'array:actionStatus,priceTierId,customerTypeId,minQuantity,maxQuantity,price'],
            'priceTiers.*.actionStatus' => ['required', 'integer', Rule::enum(PriceTierActionStatus::class)],
            'priceTiers.*.priceTierId' => ['sometimes', 'integer', 'min:1'],
            'priceTiers.*.customerTypeId' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'priceTiers.*.minQuantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'priceTiers.*.maxQuantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $seen = [];
            $tiers = $this->input('priceTiers', []);
            if (! is_array($tiers)) {
                return;
            }

            foreach ($tiers as $index => $tier) {
                if (! is_array($tier) || ! isset($tier['actionStatus'])) {
                    continue;
                }

                $action = PriceTierActionStatus::tryFrom(
                    is_int($tier['actionStatus']) ? $tier['actionStatus'] : -1
                );
                if ($action === null) {
                    $validator->errors()->add('priceTiers.'.$index.'.actionStatus', __('printing_services.messages.invalid_tier_action'));
                    continue;
                }
                $id = $tier['priceTierId'] ?? null;
                if ($action === PriceTierActionStatus::CREATE) {
                    if (array_key_exists('priceTierId', $tier)) {
                        $validator->errors()->add('priceTiers.'.$index.'.priceTierId', __('printing_services.messages.create_tier_has_id'));
                    }
                } elseif (in_array($action, [PriceTierActionStatus::UPDATE, PriceTierActionStatus::DELETE], true) && ! self::validPositiveInteger($id)) {
                    $validator->errors()->add('priceTiers.'.$index.'.priceTierId', __('printing_services.messages.tier_id_required'));
                }

                if ($id !== null && self::validPositiveInteger($id)) {
                    if (isset($seen[(string) $id])) {
                        $validator->errors()->add('priceTiers.'.$index.'.priceTierId', __('printing_services.messages.duplicate_tier_command'));
                    }
                    $seen[(string) $id] = true;
                }

                if (in_array($action, [PriceTierActionStatus::CREATE, PriceTierActionStatus::UPDATE], true)) {
                    $this->checkTierFields($validator, $tier, $index, $action === PriceTierActionStatus::UPDATE);
                }
            }
        });
    }
}

