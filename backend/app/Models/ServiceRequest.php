<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_ANALYZING = 'analyzing';

    public const STATUS_READY_FOR_ASSIGNMENT = 'ready_for_assignment';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TRANSITIONS = [
        self::STATUS_NEW => [self::STATUS_ANALYZING, self::STATUS_CANCELLED],
        self::STATUS_ANALYZING => [self::STATUS_READY_FOR_ASSIGNMENT, self::STATUS_CANCELLED],
        self::STATUS_READY_FOR_ASSIGNMENT => [self::STATUS_ASSIGNED, self::STATUS_CANCELLED],
        self::STATUS_ASSIGNED => [self::STATUS_SCHEDULED, self::STATUS_CANCELLED],
        self::STATUS_SCHEDULED => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [self::STATUS_REVIEWED],
        self::STATUS_REVIEWED => [self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'customer_id', 'service_type', 'description', 'status', 'category', 'priority',
        'required_skill', 'estimated_duration_minutes', 'preferred_date', 'preferred_time',
        'scheduled_at', 'address',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'scheduled_at' => 'datetime',
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function aiAnalysis()
    {
        return $this->hasOne(AiAnalysis::class);
    }

    public function assignment()
    {
        return $this->hasOne(Assignment::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(StatusHistory::class);
    }

    public function serviceNote()
    {
        return $this->hasOne(ServiceNote::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
