<?php
namespace Modules\Deliveries\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeliveryIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $ready = $this->route()->getName() === 'api.deliveries.ready.index';
        $keys = $ready ? 'search,customerId' : 'search,dateFrom,dateTo,status';
        return [
            'filter' => ['sometimes', 'array:'.$keys],
            'filter.search' => ['sometimes', 'string', 'min:1', 'max:200'],
            'filter.customerId' => [$ready ? 'sometimes' : 'prohibited', 'integer', 'min:1'],
            'filter.dateFrom' => [$ready ? 'prohibited' : 'sometimes', 'date_format:Y-m-d'],
            'filter.dateTo' => [$ready ? 'prohibited' : 'sometimes', 'date_format:Y-m-d'],
            'filter.status' => [$ready ? 'prohibited' : 'sometimes', 'integer', 'in:1,2'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:'.($ready
                ? 'dueDate,-dueDate,id,-id' : 'deliveredAt,-deliveredAt,id,-id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v): void {
            $a = $this->input('filter.dateFrom');
            $b = $this->input('filter.dateTo');
            if (! $v->errors()->has('filter.dateFrom') && ! $v->errors()->has('filter.dateTo')
                && is_string($a) && is_string($b) && $a > $b)
                $v->errors()->add('filter.dateTo', __('deliveries.messages.invalid_date_range'));
        }];
    }
}
