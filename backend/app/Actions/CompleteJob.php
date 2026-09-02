<?php

namespace App\Actions;

use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves a job from IN_PROGRESS to COMPLETED. Also flips the technician's
 * assignment status back to available handling is left to the caller/
 * observer layer to keep this action single-purpose.
 */
class CompleteJob
{
    public function execute(ServiceRequest $request): ServiceRequest
    {
        if (! $request->canTransitionTo(ServiceRequest::STATUS_COMPLETED)) {
            throw new RuntimeException("Service request #{$request->id} cannot be completed from status [{$request->status}].");
        }

        if (! $request->serviceNote?->isFinalized()) {
            throw new RuntimeException("Service request #{$request->id} cannot be completed until its service report has been finalized.");
        }

        return DB::transaction(function () use ($request) {
            $from = $request->status;
            $request->update(['status' => ServiceRequest::STATUS_COMPLETED]);

            if ($request->assignment) {
                $request->assignment->update(['status' => 'completed']);
                $request->assignment->technician?->update(['status' => 'available']);
            }

            StatusHistory::create([
                'service_request_id' => $request->id,
                'from_status' => $from,
                'to_status' => ServiceRequest::STATUS_COMPLETED,
                'changed_by' => Auth::id(),
                'created_at' => now(),
            ]);

            return $request->fresh();
        });
    }
}
