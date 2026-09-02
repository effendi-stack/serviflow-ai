<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'category' => $this->category,
            'priority' => $this->priority,
            'issue' => $this->issue,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'required_skill' => $this->required_skill,
            'confidence' => (float) $this->confidence,
            'accepted_by_admin' => $this->accepted_by_admin,
        ];
    }
}
