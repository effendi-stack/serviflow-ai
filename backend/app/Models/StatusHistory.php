<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'status_history';

    protected $fillable = [
        'service_request_id', 'from_status', 'to_status', 'changed_by', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
