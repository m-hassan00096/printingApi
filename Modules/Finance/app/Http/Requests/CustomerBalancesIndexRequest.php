<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Validation\Rule;

class CustomerBalancesIndexRequest extends FinanceIndexRequest
{
    protected function filterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'integer', Rule::in([0, 1, 2])],
        ];
    }

    protected function sortableFields(): array
    {
        return ['-id', 'id', 'name', '-name', '-balance', 'balance'];
    }
}
