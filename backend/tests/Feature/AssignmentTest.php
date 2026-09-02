<?php

use App\Actions\AssignTechnician;
use App\Models\Assignment;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;

it('rejects assigning a technician who lacks the required skill', function () {
    $technician = Technician::factory()->create(['skills' => ['plumbing']]);
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
    ]);

    expect(fn () => app(AssignTechnician::class)->execute($request, $technician))
        ->toThrow(RuntimeException::class);
});

it('assigns an eligible technician and records the status transition', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $technician = Technician::factory()->create(['skills' => ['ac_repair'], 'status' => 'available']);
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
    ]);

    $assignment = app(AssignTechnician::class)->execute($request, $technician, wasAiRecommended: true, aiScore: 91.5);

    expect($assignment)->toBeInstanceOf(Assignment::class);
    expect($request->fresh()->status)->toBe(ServiceRequest::STATUS_ASSIGNED);
    expect($request->fresh()->statusHistory()->latest()->first()->to_status)->toBe(ServiceRequest::STATUS_ASSIGNED);
});

it('rejects double-booking a technician for an overlapping scheduled time', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);

    $first = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
        'scheduled_at' => now()->addDay()->setTime(14, 0),
        'estimated_duration_minutes' => 90,
    ]);
    app(AssignTechnician::class)->execute($first, $technician);

    $overlapping = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
        'scheduled_at' => now()->addDay()->setTime(14, 30),
        'estimated_duration_minutes' => 60,
    ]);

    expect(fn () => app(AssignTechnician::class)->execute($overlapping, $technician))
        ->toThrow(RuntimeException::class);
});
