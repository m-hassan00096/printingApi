<?php

namespace Modules\Identity\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\RoleStatus;

class IndexRolesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,status'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'filter.status' => ['sometimes', 'nullable', Rule::in([
                RoleStatus::ACTIVE->value, RoleStatus::INACTIVE->value,
            ])],
            'sort' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
