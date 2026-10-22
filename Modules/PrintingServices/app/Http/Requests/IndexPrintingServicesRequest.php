<?php

namespace Modules\PrintingServices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PrintingServices\Enums\PrintingServiceStatus;

class IndexPrintingServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,status'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.status' => ['sometimes', 'nullable', 'integer', Rule::enum(PrintingServiceStatus::class)],
            'sort' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}

