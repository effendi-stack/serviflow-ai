<?php

namespace App\Actions;

use App\Models\ServiceNote;
use App\Models\ServiceRequest;
use RuntimeException;

/**
 * Technician-facing notes/photos capture (spec section 4.8, roadmap item
 * 3). Deliberately separate from report generation and finalization --
 * a technician can save notes multiple times while working the job
 * before ever triggering the AI draft.
 */
class UpsertServiceNote
{
    public function execute(ServiceRequest $request, string $rawNotes, ?array $photoPaths = null): ServiceNote
    {
        if ($request->status !== ServiceRequest::STATUS_IN_PROGRESS) {
            throw new RuntimeException("Service request #{$request->id} is not in progress; notes can only be recorded while a job is underway.");
        }

        $technicianId = $request->assignment?->technician_id;

        if (! $technicianId) {
            throw new RuntimeException("Service request #{$request->id} has no assigned technician.");
        }

        $note = $request->serviceNote;

        if ($note && $note->isFinalized()) {
            throw new RuntimeException("Service request #{$request->id} already has a finalized report; notes can no longer be edited.");
        }

        return $request->serviceNote()->updateOrCreate(
            ['service_request_id' => $request->id],
            array_filter([
                'technician_id' => $technicianId,
                'raw_notes' => $rawNotes,
                'photo_paths' => $photoPaths,
            ], fn ($value) => ! is_null($value))
        );
    }
}
