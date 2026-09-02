<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        return match ($user->role) {
            'admin' => true,
            'technician' => $serviceRequest->assignment?->technician->user_id === $user->id,
            'customer' => $serviceRequest->customer->user_id === $user->id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function assign(User $user): bool
    {
        return $user->isAdmin();
    }

    public function updateStatus(User $user, ServiceRequest $serviceRequest): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTechnician() && $serviceRequest->assignment?->technician->user_id === $user->id;
    }
}
