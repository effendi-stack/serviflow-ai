<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Shared by any controller acting on a service request: a technician
     * may only act on jobs assigned to them; admins pass through.
     */
    protected function authorizeTechnicianOnJob(ServiceRequest $serviceRequest): void
    {
        $user = Auth::user();

        if ($user->isTechnician() && $serviceRequest->assignment?->technician->user_id !== $user->id) {
            abort(403);
        }
    }

    protected function authorizeCustomerOnJob(ServiceRequest $serviceRequest): void
    {
        $user = Auth::user();

        if ($user->isCustomer() && $serviceRequest->customer->user_id !== $user->id) {
            abort(403);
        }
    }
}
