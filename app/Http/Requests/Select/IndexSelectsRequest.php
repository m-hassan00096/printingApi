<?php

namespace App\Http\Requests\Select;

use Illuminate\Foundation\Http\FormRequest;

class IndexSelectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The route handles authentication; providers handle authorization.
    }

    public function rules(): array
    {
        return [
            'selects' => [
                'required',
                'string',
                'max:2048',
                // 'users,roles' or URL-encoded 'users#roles'
                'regex:/\A[a-zA-Z][a-zA-Z0-9_]*(?:[,#][a-zA-Z][a-zA-Z0-9_]*)*\z/D',
            ],
            'params' => ['sometimes', 'array'],
            'params.*' => ['required', 'string', 'max:255'],
        ];
    }
}
