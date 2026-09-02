<?php

namespace App\Actions;

use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ServiceRequest::TRANSITIONS has allowed ANY_STATUS -> CANCELLED (up
 * through IN_PROGRESS) since the state machine was first written, but
 * no action or endpoint ever triggered it -- there was simply no way
 * for a customer or admin to cancel a request. This closes that gap.
 *
 * If a technician was assigned, cancelling frees them back to
 * available, mirroring CompleteJob's handling of the same situation.
 */
class CancelServiceRequest
{
    public function execute(ServiceRequest $request, ?string $reason = null): ServiceRequest
    {
        if (! $request->canTransitionTo(ServiceRequest::STATUS_CANCELLED)) {
            throw new RuntimeException("Service request #{$request->id} cannot be cancelled from status [{$request->status}].");
        }

        return DB::transaction(function () use ($request, $reason) {
            $from = $request->status;
            $request->update(['status' => ServiceRequest::STATUS_CANCELLED]);

            if ($request->assignment && in_array($request->assignment->status, ['assigned', 'started'], true)) {
                $request->assignment->update(['status' => 'cancelled']);
                $request->assignment->technician?->update(['status' => 'available']);
            }

            StatusHistory::create([
                'service_request_id' => $request->id,
                'from_status' => $from,
                'to_status' => ServiceRequest::STATUS_CANCELLED,
                'changed_by' => Auth::id(),
                'note' => $reason,
                'created_at' => now(),
            ]);

            return $request->fresh();
        });
    }
}
