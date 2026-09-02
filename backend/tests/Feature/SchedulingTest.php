<?php

use App\Actions\AssignTechnician;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Services\ServiceRequest\SchedulingService;

function assignedRequest(Technician $technician, ?DateTimeInterface $scheduledAt = null, int $durationMinutes = 90): ServiceRequest
{
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => $technician->skills[0] ?? null,
        'estimated_duration_minutes' => $durationMinutes,
    ]);

    app(AssignTechnician::class)->execute($request, $technician);

    if ($scheduledAt) {
        $request->forceFill(['scheduled_at' => $scheduledAt, 'status' => ServiceRequest::STATUS_SCHEDULED])->save();
    }

    return $request->fresh();
}

it('detects no conflict for a technician with no other appointments', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->make([
        'scheduled_at' => now()->addDay()->setTime(10, 0),
        'estimated_duration_minutes' => 60,
    ]);

    expect(app(SchedulingService::class)->hasConflict($technician, $request))->toBeFalse();
});

it('detects a conflict when windows fully overlap', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    assignedRequest($technician, now()->addDay()->setTime(10, 0), 90);

    $candidate = ServiceRequest::factory()->make([
        'scheduled_at' => now()->addDay()->setTime(10, 0),
        'estimated_duration_minutes' => 90,
    ]);

    expect(app(SchedulingService::class)->hasConflict($technician, $candidate))->toBeTrue();
});

it('detects a conflict on partial overlap where the other job starts earlier and runs into the window', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    // Existing job: 09:00 + 90min + 30min buffer either side -> occupies ~08:30-11:00
    assignedRequest($technician, now()->addDay()->setTime(9, 0), 90);

    // New job requested at 10:45 -- the earlier job's tail end still overlaps it.
    // This is exactly the case the old whereBetween-on-raw-start check missed.
    $candidate = ServiceRequest::factory()->make([
        'scheduled_at' => now()->addDay()->setTime(10, 45),
        'estimated_duration_minutes' => 60,
    ]);

    expect(app(SchedulingService::class)->hasConflict($technician, $candidate))->toBeTrue();
});

it('detects a conflict on full containment of one window inside another', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    assignedRequest($technician, now()->addDay()->setTime(9, 0), 240);

    $candidate = ServiceRequest::factory()->make([
        'scheduled_at' => now()->addDay()->setTime(10, 0),
        'estimated_duration_minutes' => 30,
    ]);

    expect(app(SchedulingService::class)->hasConflict($technician, $candidate))->toBeTrue();
});

it('does not flag a conflict once the buffer gap clears both windows', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    // Existing 09:00 + 60min + 30min buffer -> ends ~10:30
    assignedRequest($technician, now()->addDay()->setTime(9, 0), 60);

    // New job starts well clear of the buffered end.
    $candidate = ServiceRequest::factory()->make([
        'scheduled_at' => now()->addDay()->setTime(11, 30),
        'estimated_duration_minutes' => 60,
    ]);

    expect(app(SchedulingService::class)->hasConflict($technician, $candidate))->toBeFalse();
});

it('excludes the request being rescheduled from its own conflict check', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = assignedRequest($technician, now()->addDay()->setTime(9, 0), 90);

    expect(
        app(SchedulingService::class)->hasConflict($technician, $request, excludingRequestId: $request->id)
    )->toBeFalse();
});

it('schedules an assigned request and transitions it to scheduled', function () {
    $admin = User::factory()->admin()->create();
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
    ]);
    app(AssignTechnician::class)->execute($request, $technician);

    $scheduledAt = now()->addDays(2)->setTime(14, 0);

    $response = $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/schedule", [
        'scheduled_at' => $scheduledAt->toIso8601String(),
    ]);

    $response->assertOk()->assertJsonPath('data.status', ServiceRequest::STATUS_SCHEDULED);
    expect($request->fresh()->scheduled_at->equalTo($scheduledAt))->toBeTrue();
    expect($request->fresh()->statusHistory()->orderByDesc('id')->first()->to_status)->toBe(ServiceRequest::STATUS_SCHEDULED);
});

it('rejects scheduling a technician into a conflicting slot over HTTP', function () {
    $admin = User::factory()->admin()->create();
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    assignedRequest($technician, now()->addDay()->setTime(14, 0), 90);

    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
    ]);
    app(AssignTechnician::class)->execute($request, $technician);

    $response = $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/schedule", [
        'scheduled_at' => now()->addDay()->setTime(14, 30)->toIso8601String(),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('scheduled_at');
    expect($request->fresh()->status)->toBe(ServiceRequest::STATUS_ASSIGNED);
});

it('rejects scheduling from a status that is not assigned', function () {
    $admin = User::factory()->admin()->create();
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_NEW]);

    $this->actingAs($admin)
        ->postJson("/api/service-requests/{$request->id}/schedule", [
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])
        ->assertStatus(422);
});

it('rejects scheduling by a non-admin user', function () {
    $technician = User::factory()->technician()->create();
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_ASSIGNED]);

    $this->actingAs($technician)
        ->postJson("/api/service-requests/{$request->id}/schedule", [
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])
        ->assertStatus(403);
});
