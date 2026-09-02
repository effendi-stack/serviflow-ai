<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AskKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'technician'], true);
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:1000'],
        ];
    }
}
