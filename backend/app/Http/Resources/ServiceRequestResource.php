<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_type' => $this->service_type,
            'description' => $this->description,
            'status' => $this->status,
            'category' => $this->category,
            'priority' => $this->priority,
            'required_skill' => $this->required_skill,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'preferred_date' => $this->preferred_date?->toDateString(),
            'preferred_time' => $this->preferred_time,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'address' => $this->address,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'ai_analysis' => new AiAnalysisResource($this->whenLoaded('aiAnalysis')),
            'assignment' => new AssignmentResource($this->whenLoaded('assignment')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'service_note' => new ServiceNoteResource($this->whenLoaded('serviceNote')),
            'status_history' => StatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'review' => new ReviewResource($this->whenLoaded('review')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
