<?php

namespace Modules\Identity\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Identity\Enums\RoleStatus;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->ignore($user),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'avatar' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'status' => [
                'sometimes',
                'required',
                Rule::in([UserStatus::ACTIVE->value, UserStatus::INACTIVE->value]),
            ],
            'roleId' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('roles', 'id')
                    ->where('guard_name', 'web')
                    ->where('status', RoleStatus::ACTIVE->value),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:255'],
            'passwordConfirmation' => ['required_with:password', 'string', 'same:password'],
        ];
    }
}
