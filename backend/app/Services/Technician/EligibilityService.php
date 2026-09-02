<?php

namespace App\Services\Technician;

use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Services\ServiceRequest\SchedulingService;
use Illuminate\Support\Collection;

/**
 * Deterministic eligibility rules -- this is the gate the AI never gets
 * to open or close (spec section 4.5). A technician is eligible only if
 * they have the required skill, aren't off duty, and have no conflicting
 * assignment at the requested time.
 */
class EligibilityService
{
    public function __construct(protected SchedulingService $scheduling) {}

    /**
     * @return Collection<int, Technician>
     */
    public function eligibleFor(ServiceRequest $request): Collection
    {
        return Technician::query()
            ->where('status', '!=', 'off_duty')
            ->get()
            ->filter(fn (Technician $t) => $this->isEligible($t, $request))
            ->values();
    }

    public function isEligible(Technician $technician, ServiceRequest $request): bool
    {
        if ($technician->status === 'off_duty') {
            return false;
        }

        if ($request->required_skill && ! $technician->hasSkill($request->required_skill)) {
            return false;
        }

        if ($request->scheduled_at && $this->scheduling->hasConflict($technician, $request)) {
            return false;
        }

        return true;
    }
}
