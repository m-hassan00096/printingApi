<?php
namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class FinanceIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, array> */
    abstract protected function filterRules(): array;

    /** @return list<string> */
    protected function sortableFields(): array { return []; }

    public function rules(): array
    {
        $filters = $this->filterRules();
        $sortFields = $this->sortableFields();

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', 'array:'.implode(',', array_keys($filters))],
            ...collect($filters)->mapWithKeys(fn (array $rules, string $key) => ['filter.'.$key => $rules])->all(),
            'sort' => $sortFields === [] ? ['prohibited'] : ['sometimes', 'string', Rule::in($sortFields)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('filter.dateFrom') || $validator->errors()->has('filter.dateTo')) {
                    return;
                }
                $from = $this->input('filter.dateFrom');
                $to = $this->input('filter.dateTo');
                if (is_string($from) && is_string($to) && $to < $from) {
                    $validator->errors()->add('filter.dateTo',
                        __('validation.after_or_equal', ['attribute' => 'filter.dateTo', 'date' => $from]));
                }
            },
        ];
    }
}
