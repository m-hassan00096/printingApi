<?php
namespace Modules\Production\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:column,customerId,serviceId,search,myDesignWork,lateOnly'],
            'filter.column' => ['sometimes', 'integer', 'in:0,1,2,3'],
            'filter.customerId' => ['sometimes', 'integer', 'min:1'],
            'filter.serviceId' => ['sometimes', 'integer', 'min:1'],
            'filter.search' => ['sometimes', 'string', 'max:200'],
            'filter.myDesignWork' => ['sometimes', 'integer', 'in:1,2'],
            'filter.lateOnly' => ['sometimes', 'in:0,1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:id,-id,dueDate,-dueDate,priority'],
        ];
    }
}
