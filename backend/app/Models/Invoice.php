<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'service_request_id', 'status', 'labor_cost', 'parts_cost',
        'total', 'line_items', 'issued_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'line_items' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
