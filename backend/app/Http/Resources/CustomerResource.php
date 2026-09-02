<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'service_requests_count' => $this->whenCounted('serviceRequests'),
            'recent_requests' => $this->whenLoaded('serviceRequests', fn () => $this->serviceRequests->map(fn ($r) => [
                'id' => $r->id,
                'service_type' => $r->service_type,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
            ])),
        ];
    }
}
