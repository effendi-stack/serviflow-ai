<?php

namespace App\Http\Controllers\Api;

use App\Actions\FinalizeServiceReport;
use App\Actions\GenerateJobSummary;
use App\Actions\UpsertServiceNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinalizeServiceReportRequest;
use App\Http\Requests\UpsertServiceNoteRequest;
use App\Http\Resources\ServiceNoteResource;
use App\Http\Support\ServerSentEvents;
use App\Models\ServiceRequest;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Technician-facing notes -> AI draft -> human finalization workflow
 * (spec sections 4.8-4.9, roadmap items 3-5). Kept as its own controller
 * since it's a distinct resource (the job's service note) from the
 * service request lifecycle itself.
 */
class ServiceNoteController extends Controller
{
    public function store(UpsertServiceNoteRequest $request, ServiceRequest $serviceRequest, UpsertServiceNote $action)
    {
        $this->authorizeTechnicianOnJob($serviceRequest);

        try {
            $note = $action->execute(
                $serviceRequest,
                $request->validated('raw_notes'),
                $request->validated('photo_paths'),
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['raw_notes' => [$e->getMessage()]]);
        }

        return new ServiceNoteResource($note);
    }

    public function generateReport(ServiceRequest $serviceRequest, GenerateJobSummary $action)
    {
        $this->authorizeTechnicianOnJob($serviceRequest);

        $note = $serviceRequest->serviceNote;

        abort_if(! $note, 422, 'Record notes for this job before generating a report.');
        abort_if($note->isFinalized(), 422, 'This report has already been finalized.');

        return new ServiceNoteResource($action->execute($note));
    }

    /**
     * Same as generateReport, but streams the draft as it is written
     * (text/event-stream): "token" events carry text fragments, then
     * "done" carries the saved note, or "error" if generation failed.
     */
    public function streamReport(ServiceRequest $serviceRequest, GenerateJobSummary $action)
    {
        $this->authorizeTechnicianOnJob($serviceRequest);

        $note = $serviceRequest->serviceNote;

        abort_if(! $note, 422, 'Record notes for this job before generating a report.');
        abort_if($note->isFinalized(), 422, 'This report has already been finalized.');

        return ServerSentEvents::response(function (callable $send) use ($action, $note) {
            $note = $action->execute($note, fn (string $token) => $send('token', $token));

            if ($note->ai_generated_report === null) {
                $send('error', ['message' => 'The AI report could not be generated. Please try again.']);

                return;
            }

            $send('done', (new ServiceNoteResource($note))->resolve());
        });
    }

    public function finalize(FinalizeServiceReportRequest $request, ServiceRequest $serviceRequest, FinalizeServiceReport $action)
    {
        $this->authorizeTechnicianOnJob($serviceRequest);

        try {
            $note = $action->execute($serviceRequest, $request->validated('final_report'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['final_report' => [$e->getMessage()]]);
        }

        return new ServiceNoteResource($note);
    }
}
