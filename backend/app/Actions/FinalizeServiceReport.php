<?php

namespace App\Actions;

use App\Models\ServiceNote;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * The human-in-the-loop checkpoint required by spec section 4.9 / roadmap
 * item 5: an AI-drafted report never becomes the official record on its
 * own. The technician's edited (or freshly written) text is stored in
 * final_report, kept separate from ai_generated_report, with who and
 * when recorded. Finalizing is a one-way action -- once set, notes can
 * no longer be edited (enforced in UpsertServiceNote).
 */
class FinalizeServiceReport
{
    public function execute(ServiceRequest $request, string $finalReport): ServiceNote
    {
        $note = $request->serviceNote;

        if (! $note) {
            throw new RuntimeException("Service request #{$request->id} has no service notes to finalize yet.");
        }

        if ($note->isFinalized()) {
            throw new RuntimeException("Service request #{$request->id}'s report has already been finalized.");
        }

        $note->update([
            'final_report' => $finalReport,
            'finalized_by' => Auth::id(),
            'finalized_at' => now(),
        ]);

        return $note->fresh();
    }
}
