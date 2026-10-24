<?php
namespace Modules\Production\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionHistoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
