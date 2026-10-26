<?php
namespace Modules\Production\Http\Requests;

class ProductionSummaryRequest extends ProductionIndexRequest
{
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:customerId,serviceId,search,myDesignWork,lateOnly'],
            'filter.customerId' => ['sometimes', 'integer', 'min:1'],
            'filter.serviceId' => ['sometimes', 'integer', 'min:1'],
            'filter.search' => ['sometimes', 'string', 'max:200'],
            'filter.myDesignWork' => ['sometimes', 'integer', 'in:1,2'],
            'filter.lateOnly' => ['sometimes', 'in:0,1'],
        ];
    }
}
