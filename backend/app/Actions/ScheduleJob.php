<?php

namespace App\Actions;

use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use App\Services\ServiceRequest\SchedulingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves a request from ASSIGNED to SCHEDULED and persists the confirmed
 * appointment time. The conflict check is the enforcement point for
 * appointment double-booking (spec section 4.6) -- plain PHP/SQL, never
 * delegated to the AI.
 */
class ScheduleJob
{
    public function __construct(protected SchedulingService $scheduling) {}

    public function execute(ServiceRequest $request, \DateTimeInterface $scheduledAt): ServiceRequest
    {
        if (! $request->canTransitionTo(ServiceRequest::STATUS_SCHEDULED)) {
            throw new RuntimeException("Service request #{$request->id} cannot be scheduled from status [{$request->status}].");
        }

        $technician = $request->assignment?->technician;

        if (! $technician) {
            throw new RuntimeException("Service request #{$request->id} has no assigned technician to schedule.");
        }

        // Set in-memory only for the conflict check below -- not saved
        // unless the check passes.
        $request->scheduled_at = $scheduledAt;

        if ($this->scheduling->hasConflict($technician, $request, excludingRequestId: $request->id)) {
            throw new RuntimeException("Technician #{$technician->id} already has a conflicting appointment at that time.");
        }

        return DB::transaction(function () use ($request, $scheduledAt) {
            $from = $request->status;
            $request->status = ServiceRequest::STATUS_SCHEDULED;
            $request->save();

            StatusHistory::create([
                'service_request_id' => $request->id,
                'from_status' => $from,
                'to_status' => ServiceRequest::STATUS_SCHEDULED,
                'changed_by' => Auth::id(),
                'note' => 'Scheduled for '.$scheduledAt->format('Y-m-d H:i').'.',
                'created_at' => now(),
            ]);

            return $request->fresh();
        });
    }
}
