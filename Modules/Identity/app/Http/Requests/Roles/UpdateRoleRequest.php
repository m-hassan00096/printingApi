<?php

namespace Modules\Identity\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'nameAr' => [
                'sometimes', 'required', 'string', 'min:2', 'max:150',
                Rule::unique('role_translations', 'alias')
                    ->where('locale', 'ar')
                    ->where(fn ($query) => $query->where('role_id', '!=', $role->getKey())),
            ],
            'status' => [
                'sometimes', 'required', 'integer',
                Rule::in([RoleStatus::ACTIVE->value, RoleStatus::INACTIVE->value]),
            ],
            'permissionIds' => ['sometimes', 'array', 'max:500'],
            'permissionIds.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ];
    }
}
