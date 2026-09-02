<?php

namespace App\Actions;

use App\Models\Assignment;
use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use App\Models\Technician;
use App\Services\Technician\EligibilityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Final assignment write -- always performed by Laravel, after an admin
 * has reviewed (and optionally overridden) the AI's ranking. This is the
 * enforcement point for "AI ranks, Laravel assigns" (spec section 4.5).
 */
class AssignTechnician
{
    public function __construct(protected EligibilityService $eligibility) {}

    public function execute(
        ServiceRequest $request,
        Technician $technician,
        bool $wasAiRecommended = false,
        ?float $aiScore = null,
        ?string $aiRationale = null,
    ): Assignment {
        if (! $request->canTransitionTo(ServiceRequest::STATUS_ASSIGNED) && $request->status !== ServiceRequest::STATUS_ASSIGNED) {
            throw new RuntimeException("Service request #{$request->id} cannot be assigned from status [{$request->status}].");
        }

        if (! $this->eligibility->isEligible($technician, $request)) {
            throw new RuntimeException("Technician #{$technician->id} is not eligible for service request #{$request->id}.");
        }

        return DB::transaction(function () use ($request, $technician, $wasAiRecommended, $aiScore, $aiRationale) {
            $assignment = Assignment::updateOrCreate(
                ['service_request_id' => $request->id],
                [
                    'technician_id' => $technician->id,
                    'assigned_by' => Auth::id(),
                    'status' => 'assigned',
                    'ai_rank_score' => $aiScore,
                    'ai_rationale' => $aiRationale,
                    'was_ai_recommended' => $wasAiRecommended,
                ]
            );

            $from = $request->status;
            $request->update(['status' => ServiceRequest::STATUS_ASSIGNED]);

            StatusHistory::create([
                'service_request_id' => $request->id,
                'from_status' => $from,
                'to_status' => ServiceRequest::STATUS_ASSIGNED,
                'changed_by' => Auth::id(),
                'note' => "Assigned to technician #{$technician->id}.",
                'created_at' => now(),
            ]);

            return $assignment;
        });
    }
}
