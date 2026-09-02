<?php

namespace App\Http\Requests;

use App\AI\TicketClassifier;
use Illuminate\Foundation\Http\FormRequest;

class ReviewAiAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'in:'.implode(',', TicketClassifier::ALLOWED_CATEGORIES)],
            'priority' => ['required', 'string', 'in:'.implode(',', TicketClassifier::ALLOWED_PRIORITIES)],
            'required_skill' => ['required', 'string', 'max:100'],
            'estimated_duration_minutes' => ['required', 'integer', 'between:15,480'],
        ];
    }
}
