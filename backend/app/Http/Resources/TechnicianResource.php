<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'skills' => $this->skills,
            'status' => $this->status,
            'rating_avg' => (float) $this->rating_avg,
            'active_jobs_count' => $this->whenCounted('active_jobs_count'),
            'recent_jobs' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($a) => [
                'service_request_id' => $a->service_request_id,
                'service_type' => $a->serviceRequest?->service_type,
                'status' => $a->serviceRequest?->status,
                'scheduled_at' => $a->serviceRequest?->scheduled_at?->toIso8601String(),
            ])),
        ];
    }
}
