<?php

namespace App\Services\ServiceRequest;

use App\Models\ServiceRequest;
use App\Models\Technician;
use Carbon\Carbon;

/**
 * Enforces appointment conflicts with plain SQL/business logic -- never
 * delegated to the LLM (spec section 4.6). A technician can't be double
 * booked; jobs are assumed to occupy their estimated_duration_minutes
 * (falling back to 120 minutes when unknown) plus travel buffer.
 */
class SchedulingService
{
    private const TRAVEL_BUFFER_MINUTES = 30;

    private const DEFAULT_DURATION_MINUTES = 120;

    /**
     * True interval overlap: [existing_start, existing_end) intersects
     * [new_start, new_end). Comparing only whether the *other* job's raw
     * scheduled_at timestamp falls inside the new window (the previous
     * implementation) misses conflicts where the other job starts earlier
     * but its own duration runs into the new window -- so both windows'
     * full start/end bounds are computed and compared here instead.
     *
     * Pass $excludingRequestId when checking a request against itself
     * (e.g. rescheduling), so its own assignment doesn't count as a
     * conflict with itself.
     */
    public function hasConflict(Technician $technician, ServiceRequest $request, ?int $excludingRequestId = null): bool
    {
        if (! $request->scheduled_at) {
            return false;
        }

        [$newStart, $newEnd] = $this->window($request->scheduled_at, $request->estimated_duration_minutes);

        // One indexed EXISTS query instead of loading every active
        // assignment into PHP (spec v2 section 10.4). Same rule as before:
        // both windows include the travel buffer on each side, and they
        // conflict when otherStart < newEnd AND otherEnd > newStart.
        return $technician->assignments()
            ->whereIn('status', ['assigned', 'started'])
            ->when($excludingRequestId, fn ($query) => $query->where('service_request_id', '!=', $excludingRequestId))
            ->whereHas('serviceRequest', fn ($query) => $query
                ->whereNotNull('scheduled_at')
                ->whereRaw(
                    'scheduled_at - make_interval(mins => ?) < ?',
                    [self::TRAVEL_BUFFER_MINUTES, $newEnd]
                )
                ->whereRaw(
                    'scheduled_at + make_interval(mins => COALESCE(estimated_duration_minutes, ?) + ?) > ?',
                    [self::DEFAULT_DURATION_MINUTES, self::TRAVEL_BUFFER_MINUTES, $newStart]
                ))
            ->exists();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(\DateTimeInterface $scheduledAt, ?int $durationMinutes): array
    {
        $duration = $durationMinutes ?? self::DEFAULT_DURATION_MINUTES;

        return [
            Carbon::parse($scheduledAt)->subMinutes(self::TRAVEL_BUFFER_MINUTES),
            Carbon::parse($scheduledAt)->addMinutes($duration + self::TRAVEL_BUFFER_MINUTES),
        ];
    }

    public function isAvailable(Technician $technician, \DateTimeInterface $at, int $durationMinutes): bool
    {
        $fakeRequest = new ServiceRequest([
            'scheduled_at' => $at,
            'estimated_duration_minutes' => $durationMinutes,
        ]);

        return ! $this->hasConflict($technician, $fakeRequest);
    }
}
