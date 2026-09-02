<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['required', 'integer', 'exists:technicians,id'],
            'was_ai_recommended' => ['sometimes', 'boolean'],
            'ai_score' => ['sometimes', 'nullable', 'numeric'],
            'ai_rationale' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
