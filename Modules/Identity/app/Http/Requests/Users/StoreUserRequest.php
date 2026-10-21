<?php

namespace Modules\Identity\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')],
            'address' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'status' => [
                'sometimes',
                'required',
                Rule::in([UserStatus::ACTIVE->value, UserStatus::INACTIVE->value]),
            ],
            'roleId' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')
                    ->where('guard_name', 'web')
                    ->where('status', RoleStatus::ACTIVE->value),
            ],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'passwordConfirmation' => ['required', 'string', 'same:password'],
        ];
    }
}
