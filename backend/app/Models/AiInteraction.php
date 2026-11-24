<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiInteraction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'feature', 'model', 'input', 'output',
        'confidence', 'latency_ms', 'succeeded', 'cache_hit', 'error', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'cache_hit' => 'boolean',
            'confidence' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }
}
