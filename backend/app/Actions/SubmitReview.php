<?php

namespace App\Actions;

use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Customer review (spec section 4.10, roadmap item 6). A completed job
 * gets exactly one review, and submitting it is what closes the loop --
 * there's no further admin step described for a reviewed job in this
 * system, so REVIEWED -> CLOSED happens as part of the same action
 * rather than waiting on a separate endpoint.
 */
class SubmitReview
{
    public function execute(ServiceRequest $request, int $rating, ?string $comment): Review
    {
        if (! $request->canTransitionTo(ServiceRequest::STATUS_REVIEWED)) {
            throw new RuntimeException("Service request #{$request->id} cannot be reviewed from status [{$request->status}].");
        }

        if ($request->review) {
            throw new RuntimeException("Service request #{$request->id} has already been reviewed.");
        }

        return DB::transaction(function () use ($request, $rating, $comment) {
            $review = Review::create([
                'service_request_id' => $request->id,
                'technician_id' => $request->assignment?->technician_id,
                'rating' => $rating,
                'comment' => $comment,
            ]);

            $this->transition($request, ServiceRequest::STATUS_REVIEWED);
            $this->transition($request, ServiceRequest::STATUS_CLOSED);

            return $review;
        });
    }

    private function transition(ServiceRequest $request, string $to): void
    {
        $from = $request->status;
        $request->update(['status' => $to]);

        StatusHistory::create([
            'service_request_id' => $request->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => Auth::id(),
            'created_at' => now(),
        ]);
    }
}
