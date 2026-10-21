<?php

namespace Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'اسم المستخدم',
            'password' => 'كلمة المرور',
        ];
    }
}
