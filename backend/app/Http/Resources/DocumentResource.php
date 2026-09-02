<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'status' => $this->status,
            'page_count' => $this->page_count,
            'processing_error' => $this->processing_error,
            'chunk_count' => $this->whenCounted('chunks'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
