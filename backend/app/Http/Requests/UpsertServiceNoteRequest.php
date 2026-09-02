<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertServiceNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTechnician() ?? false;
    }

    public function rules(): array
    {
        return [
            'raw_notes' => ['required', 'string', 'max:4000'],
            'photo_paths' => ['sometimes', 'array'],
            'photo_paths.*' => ['string', 'max:2048'],
        ];
    }
}
