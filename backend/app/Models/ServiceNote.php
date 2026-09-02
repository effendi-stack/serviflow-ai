<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceNote extends Model
{
    protected $fillable = [
        'service_request_id', 'technician_id', 'raw_notes',
        'ai_generated_report', 'final_report', 'photo_paths',
        'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_paths' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function isFinalized(): bool
    {
        return ! is_null($this->finalized_at);
    }

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function technician()
    {
        return $this->belongsTo(Technician::class);
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
