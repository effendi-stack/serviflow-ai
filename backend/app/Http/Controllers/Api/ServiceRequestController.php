<?php

namespace App\Http\Controllers\Api;

use App\Actions\AssignTechnician;
use App\Actions\CancelServiceRequest;
use App\Actions\CompleteJob;
use App\Actions\CreateServiceRequest;
use App\Actions\ReviewAiAnalysis;
use App\Actions\ScheduleJob;
use App\AI\TechnicianRecommender;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTechnicianRequest;
use App\Http\Requests\CancelServiceRequestRequest;
use App\Http\Requests\ReviewAiAnalysisRequest;
use App\Http\Requests\ScheduleServiceRequestRequest;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Http\Resources\TechnicianResource;
use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use App\Models\Technician;
use App\Services\Technician\EligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Deliberately thin: every branch here delegates to an Action or Service.
 * The controller's only job is translating HTTP <-> those calls
 * (spec section 9.1).
 */
class ServiceRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceRequest::query()->with(['customer', 'aiAnalysis', 'assignment.technician']);

        if ($request->user()->isCustomer()) {
            $query->whereHas('customer', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        if ($request->user()->isTechnician()) {
            $query->whereHas('assignment.technician', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        return ServiceRequestResource::collection(
            $query->latest()->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StoreServiceRequestRequest $request, CreateServiceRequest $action)
    {
        $customer = $request->user()->customer;

        $serviceRequest = $action->execute($customer, $request->validated());

        return (new ServiceRequestResource($serviceRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $this->authorize('view', $serviceRequest);

        $serviceRequest->load(['customer', 'aiAnalysis', 'assignment.technician', 'invoice', 'statusHistory', 'serviceNote', 'review']);

        return new ServiceRequestResource($serviceRequest);
    }

    /**
     * Deterministic eligibility + AI ranking, returned together so the
     * admin sees both in one call before deciding who to assign.
     */
    public function recommendedTechnicians(
        ServiceRequest $serviceRequest,
        EligibilityService $eligibility,
        TechnicianRecommender $recommender,
    ) {
        $eligible = $eligibility->eligibleFor($serviceRequest);
        $ranked = $recommender->rank($serviceRequest, $eligible);

        $byId = $eligible->keyBy('id');

        $payload = collect($ranked)->map(fn ($row) => [
            'technician' => new TechnicianResource($byId[$row['technician_id']]),
            'score' => $row['score'],
            'rationale' => $row['rationale'],
        ])->values();

        return response()->json(['data' => $payload]);
    }

    public function cancel(CancelServiceRequestRequest $request, ServiceRequest $serviceRequest, CancelServiceRequest $action)
    {
        try {
            $updated = $action->execute($serviceRequest, $request->validated('reason'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['status' => [$e->getMessage()]]);
        }

        return new ServiceRequestResource($updated);
    }

    public function reviewAiAnalysis(ReviewAiAnalysisRequest $request, ServiceRequest $serviceRequest, ReviewAiAnalysis $action)
    {
        try {
            $updated = $action->execute($serviceRequest, $request->validated());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['status' => [$e->getMessage()]]);
        }

        return new ServiceRequestResource($updated);
    }

    public function assign(AssignTechnicianRequest $request, ServiceRequest $serviceRequest, AssignTechnician $action)
    {
        $technician = Technician::findOrFail($request->validated('technician_id'));

        $assignment = $action->execute(
            $serviceRequest,
            $technician,
            wasAiRecommended: (bool) $request->boolean('was_ai_recommended'),
            aiScore: $request->input('ai_score'),
            aiRationale: $request->input('ai_rationale'),
        );

        return response()->json(['data' => $assignment->load('technician')]);
    }

    public function schedule(ScheduleServiceRequestRequest $request, ServiceRequest $serviceRequest, ScheduleJob $action)
    {
        try {
            $updated = $action->execute($serviceRequest, new \DateTimeImmutable($request->validated('scheduled_at')));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['scheduled_at' => [$e->getMessage()]]);
        }

        return new ServiceRequestResource($updated);
    }

    public function start(ServiceRequest $serviceRequest)
    {
        $this->authorizeTechnicianOnJob($serviceRequest);

        abort_unless($serviceRequest->canTransitionTo(ServiceRequest::STATUS_IN_PROGRESS), 422, 'Job cannot be started from its current status.');

        $from = $serviceRequest->status;
        $serviceRequest->update(['status' => ServiceRequest::STATUS_IN_PROGRESS]);
        $serviceRequest->assignment?->update(['status' => 'started']);
        // Tracks "busy right now", not "has any assignment" -- a
        // technician can hold several future non-overlapping scheduled
        // jobs (see SchedulingService) without being unavailable for new
        // work; only an in-progress job actually occupies them.
        $serviceRequest->assignment?->technician?->update(['status' => 'on_job']);

        StatusHistory::create([
            'service_request_id' => $serviceRequest->id,
            'from_status' => $from,
            'to_status' => ServiceRequest::STATUS_IN_PROGRESS,
            'changed_by' => Auth::id(),
            'created_at' => now(),
        ]);

        return new ServiceRequestResource($serviceRequest->fresh());
    }

    public function complete(ServiceRequest $serviceRequest, CompleteJob $action)
    {
        $this->authorizeTechnicianOnJob($serviceRequest);

        try {
            $updated = $action->execute($serviceRequest);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['status' => [$e->getMessage()]]);
        }

        return new ServiceRequestResource($updated);
    }
}
