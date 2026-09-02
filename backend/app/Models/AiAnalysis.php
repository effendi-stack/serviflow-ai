<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnalysis extends Model
{
    protected $table = 'ai_analyses';

    protected $fillable = [
        'service_request_id', 'category', 'priority', 'issue',
        'estimated_duration_minutes', 'required_skill', 'confidence',
        'raw_response', 'accepted_by_admin', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'accepted_by_admin' => 'boolean',
            'confidence' => 'decimal:3',
        ];
    }

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
