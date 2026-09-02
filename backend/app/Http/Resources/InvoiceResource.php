<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'status' => $this->status,
            'labor_cost' => (float) $this->labor_cost,
            'parts_cost' => (float) $this->parts_cost,
            'total' => (float) $this->total,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'service_request_id' => $this->when(
                $this->relationLoaded('serviceRequest'),
                fn () => $this->service_request_id
            ),
            'customer_name' => $this->when(
                $this->relationLoaded('serviceRequest') && $this->serviceRequest?->relationLoaded('customer'),
                fn () => $this->serviceRequest?->customer?->name
            ),
        ];
    }
}
