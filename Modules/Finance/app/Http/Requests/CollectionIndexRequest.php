<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Finance\Enums\CollectionSource;
use Modules\Finance\Enums\CollectionStatus;
use Modules\Finance\Enums\PaymentMethod;

class CollectionIndexRequest extends FinanceIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:200'],
            'dateFrom' => ['sometimes', 'date_format:Y-m-d'],
            'dateTo' => ['sometimes', 'date_format:Y-m-d'],
            'paymentMethod' => ['sometimes', 'integer', Rule::enum(PaymentMethod::class)],
            'source' => ['sometimes', 'integer', Rule::enum(CollectionSource::class)],
            'status' => ['sometimes', 'integer', Rule::enum(CollectionStatus::class)],
        ];
    }

    protected function sortableFields(): array
    {
        return ['id', '-id', 'collectedOn', '-collectedOn'];
    }
}
