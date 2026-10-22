<?php

namespace Modules\PrintingServices\Http\Requests\Concerns;

trait LocalizedPrintingServiceValidation
{
    /**
     * Scoped messages are intentional: the project's current lang/ar/validation.php
     * still contains English defaults. Avoid changing translations for other modules.
     */
    public function messages(): array
    {
        $messages = [];

        foreach (array_keys($this->attributes()) as $attribute) {
            foreach (['required', 'string', 'array', 'list', 'integer', 'min', 'max', 'enum', 'exists'] as $rule) {
                $messages[$attribute.'.'.$rule] = __('printing_services.validation.'.$rule);
            }
        }

        return $messages;
    }

    public function attributes(): array
    {
        $fields = [
            'name' => 'name',
            'basePrice' => 'base_price',
            'status' => 'status',
            'priceTiers' => 'tiers',
            'priceTiers.*' => 'tier',
            'priceTiers.*.actionStatus' => 'action_status',
            'priceTiers.*.priceTierId' => 'tier_id',
            'priceTiers.*.customerTypeId' => 'customer_type',
            'priceTiers.*.minQuantity' => 'min_quantity',
            'priceTiers.*.maxQuantity' => 'max_quantity',
            'priceTiers.*.price' => 'tier_price',
            'filter' => 'filters',
            'filter.search' => 'search',
            'filter.status' => 'status',
            'sort' => 'sort',
            'page' => 'page',
            'perPage' => 'per_page',
            'serviceId' => 'service_id',
            'customerTypeId' => 'customer_type',
            'quantity' => 'quantity',
        ];

        $attributes = [];
        foreach ($fields as $field => $translation) {
            $attributes[$field] = __('printing_services.attributes.'.$translation);
        }

        return $attributes;
    }
}

