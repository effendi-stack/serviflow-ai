<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TechnicianResource;
use App\Models\Technician;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TechnicianController extends Controller
{
    public function index(Request $request)
    {
        $query = Technician::withCount([
            'assignments as active_jobs_count' => fn ($q) => $q->whereIn('status', ['assigned', 'started']),
        ]);

        if ($skill = $request->query('skill')) {
            $query->whereJsonContains('skills', $skill);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return TechnicianResource::collection($query->orderBy('name')->get());
    }

    public function show(Technician $technician)
    {
        $technician->loadCount([
            'assignments as active_jobs_count' => fn ($q) => $q->whereIn('status', ['assigned', 'started']),
        ])->load(['assignments' => fn ($q) => $q->latest()->limit(10)->with('serviceRequest:id,service_type,status,scheduled_at')]);

        return new TechnicianResource($technician);
    }

    /**
     * Admin can only toggle between available/off_duty -- on_job is
     * system-managed (see ServiceRequestController::start/complete) and
     * isn't something an admin sets directly.
     */
    public function updateStatus(Request $request, Technician $technician)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:available,off_duty'],
        ]);

        if ($technician->status === 'on_job') {
            throw ValidationException::withMessages([
                'status' => ['This technician is currently on a job and cannot be marked off duty until it finishes.'],
            ]);
        }

        $technician->update(['status' => $data['status']]);

        return new TechnicianResource($technician->fresh());
    }
}
