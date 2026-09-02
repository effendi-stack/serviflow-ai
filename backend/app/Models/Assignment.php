<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = [
        'service_request_id', 'technician_id', 'assigned_by', 'status',
        'ai_rank_score', 'ai_rationale', 'was_ai_recommended',
    ];

    protected function casts(): array
    {
        return [
            'was_ai_recommended' => 'boolean',
        ];
    }

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function technician()
    {
        return $this->belongsTo(Technician::class);
    }
}
