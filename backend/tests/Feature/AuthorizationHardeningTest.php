<?php

use App\Actions\AssignTechnician;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;

/**
 * ServiceRequestPolicy::view() existed with the right rule from the
 * start, but nothing ever called it -- ServiceRequestController::show()
 * only checked customer ownership, so any technician could view any
 * other technician's job detail via GET /service-requests/{id}. This
 * file is the regression check for that gap (roadmap item 11).
 */
it('lets an assigned technician view their own job', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, 'required_skill' => 'ac_repair']);
    app(AssignTechnician::class)->execute($request, $technician);

    $this->actingAs($technician->user)->getJson("/api/service-requests/{$request->id}")->assertOk();
});

it("blocks a technician from viewing a job that isn't assigned to them", function () {
    $owner = Technician::factory()->create(['skills' => ['ac_repair']]);
    $outsider = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, 'required_skill' => 'ac_repair']);
    app(AssignTechnician::class)->execute($request, $owner);

    $this->actingAs($outsider->user)->getJson("/api/service-requests/{$request->id}")->assertStatus(403);
});

it('blocks a technician from viewing an unassigned job', function () {
    $technician = Technician::factory()->create();
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_NEW]);

    $this->actingAs($technician->user)->getJson("/api/service-requests/{$request->id}")->assertStatus(403);
});

it('always lets an admin view any job regardless of assignment', function () {
    $admin = User::factory()->admin()->create();
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_NEW]);

    $this->actingAs($admin)->getJson("/api/service-requests/{$request->id}")->assertOk();
});

it("still blocks a customer from viewing another customer's job", function () {
    $owner = Customer::factory()->create();
    $intruder = Customer::factory()->create();
    $request = ServiceRequest::factory()->create(['customer_id' => $owner->id]);

    $this->actingAs($intruder->user)->getJson("/api/service-requests/{$request->id}")->assertStatus(403);
});

it('rejects unauthenticated access to protected routes', function () {
    $request = ServiceRequest::factory()->create();

    $this->getJson("/api/service-requests/{$request->id}")->assertStatus(401);
    $this->getJson('/api/service-requests')->assertStatus(401);
    $this->getJson('/api/technicians')->assertStatus(401);
});
