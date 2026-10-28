<?php
namespace Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Orders\Enums\OrderProductionStatus;
use Modules\Orders\Enums\OrderStatus;

class IndexOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,status,productionStatus,lateOnly,openOnly,readyDueToday,createdFrom,createdTo'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.status' => ['sometimes', 'nullable', 'integer', Rule::enum(OrderStatus::class)],
            'filter.productionStatus' => [
                'sometimes', 'nullable', 'integer', Rule::enum(OrderProductionStatus::class),
            ],
            'filter.lateOnly' => ['sometimes', 'in:0,1'],
            'filter.openOnly' => ['sometimes', 'in:0,1'],
            'filter.readyDueToday' => ['sometimes', 'in:0,1'],
            'filter.createdFrom' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'filter.createdTo' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'sort' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
