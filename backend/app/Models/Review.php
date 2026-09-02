<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'service_request_id', 'technician_id', 'rating', 'comment',
    ];

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function technician()
    {
        return $this->belongsTo(Technician::class);
    }
}
