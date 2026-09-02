<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Technician extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'skills', 'status', 'rating_avg', 'base_city',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function hasSkill(string $skill): bool
    {
        return in_array($skill, $this->skills ?? [], true);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }
}
