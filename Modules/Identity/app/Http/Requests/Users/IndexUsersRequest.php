<?php

namespace Modules\Identity\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\UserStatus;

class IndexUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:search,status,role'],
            'filter.search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filter.status' => [
                'sometimes',
                'nullable',
                Rule::in([UserStatus::ACTIVE->value, UserStatus::INACTIVE->value]),
            ],
            'filter.role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
