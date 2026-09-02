<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'raw_notes' => $this->raw_notes,
            'ai_generated_report' => $this->ai_generated_report,
            'final_report' => $this->final_report,
            'photo_paths' => $this->photo_paths ?? [],
            'is_finalized' => $this->isFinalized(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
