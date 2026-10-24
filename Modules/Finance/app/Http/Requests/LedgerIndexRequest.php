<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Finance\Enums\LedgerEntryType;

class LedgerIndexRequest extends FinanceIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'dateFrom' => ['sometimes', 'date_format:Y-m-d'],
            'dateTo' => ['sometimes', 'date_format:Y-m-d'],
            'type' => ['sometimes', 'integer', Rule::enum(LedgerEntryType::class)],
        ];
    }
}
