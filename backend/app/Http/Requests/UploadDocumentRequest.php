<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:manual,error_codes,sop,warranty,troubleshooting'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'], // 20MB
        ];
    }
}
