<?php
namespace Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrderFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Laravel "max" is expressed in KiB. 102400 KiB = 100 MiB.
        return ['file' => ['required', 'file', 'max:102400']];
    }
}
