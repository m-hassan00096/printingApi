<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinanceIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', 'array'],
            'filter.search' => ['sometimes', 'string', 'max:200'],
            'filter.dateFrom' => ['sometimes', 'date_format:Y-m-d'],
            'filter.dateTo' => ['sometimes', 'date_format:Y-m-d'],
            'filter.paymentMethod' => ['sometimes', 'integer', 'in:0,1,2'],
            'filter.source' => ['sometimes', 'integer', 'in:0,1,2'],
            'filter.status' => ['sometimes', 'integer', 'in:1,2'],
            'filter.type' => ['sometimes', 'integer', 'between:1,6'],
            'sort' => ['sometimes', 'string', 'in:id,-id,collectedOn,-collectedOn,refundedOn,-refundedOn'],
        ];
    }
}
