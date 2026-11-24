<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'technician' => new TechnicianResource($this->whenLoaded('technician')),
            'status' => $this->status,
            'was_ai_recommended' => $this->was_ai_recommended,
            'ai_rank_score' => $this->ai_rank_score === null ? null : (float) $this->ai_rank_score,
            'ai_rationale' => $this->ai_rationale,
        ];
    }
}
