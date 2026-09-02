<?php

namespace App\Actions;

use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Admin triage review (spec section 4.4, roadmap items 10 & 12). This is
 * the step that turns an AI suggestion (or a manual classification, if
 * the AI never ran) into the values the rest of the system actually
 * uses -- ServiceRequest::category/priority/required_skill/duration were
 * sitting unused until this action existed, which meant eligibility
 * filtering silently matched every technician (see
 * EligibilityService::isEligible's null-required_skill early-out).
 *
 * The AI's original suggestion is left untouched in ai_analyses
 * (category/priority/etc + raw_response) -- "accepted" is computed here
 * by comparing the admin's submitted values against it, rather than
 * trusting a client-supplied flag, so the audit trail can't be spoofed.
 */
class ReviewAiAnalysis
{
    public function execute(ServiceRequest $request, array $final): ServiceRequest
    {
        if ($request->status !== ServiceRequest::STATUS_READY_FOR_ASSIGNMENT) {
            throw new RuntimeException("Service request #{$request->id} cannot be triaged from status [{$request->status}]; triage happens before assignment.");
        }

        $analysis = $request->aiAnalysis;

        if ($analysis) {
            $wasAccepted = $analysis->category === $final['category']
                && $analysis->priority === $final['priority']
                && $analysis->required_skill === $final['required_skill']
                && (int) $analysis->estimated_duration_minutes === (int) $final['estimated_duration_minutes'];

            $analysis->update([
                'accepted_by_admin' => $wasAccepted,
                'reviewed_by' => Auth::id(),
            ]);
        }

        $request->update([
            'category' => $final['category'],
            'priority' => $final['priority'],
            'required_skill' => $final['required_skill'],
            'estimated_duration_minutes' => $final['estimated_duration_minutes'],
        ]);

        return $request->fresh();
    }
}
