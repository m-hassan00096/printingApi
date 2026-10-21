<?php

namespace Modules\Identity\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\RoleStatus;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nameAr' => [
                'required', 'string', 'min:2', 'max:150',
                Rule::unique('role_translations', 'alias')->where('locale', 'ar'),
            ],
            'status' => [
                'required', 'integer',
                Rule::in([RoleStatus::ACTIVE->value, RoleStatus::INACTIVE->value]),
            ],
            'permissionIds' => ['present', 'array', 'max:500'],
            'permissionIds.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ];
    }
}
