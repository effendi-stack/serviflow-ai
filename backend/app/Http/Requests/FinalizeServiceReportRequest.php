<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeServiceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTechnician() ?? false;
    }

    public function rules(): array
    {
        return [
            'final_report' => ['required', 'string', 'max:8000'],
        ];
    }
}
