<?php

use App\Actions\AssignTechnician;
use App\Actions\ScheduleJob;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;

// --- Full transition matrix (roadmap item 25) ---------------------------

it('only allows exactly the transitions declared in the state machine, and rejects every other pair', function () {
    $allStates = array_keys(ServiceRequest::TRANSITIONS);

    foreach ($allStates as $from) {
        $request = ServiceRequest::factory()->make(['status' => $from]);
        $allowed = ServiceRequest::TRANSITIONS[$from];

        foreach ($allStates as $to) {
            $shouldAllow = in_array($to, $allowed, true);
            expect($request->canTransitionTo($to))
                ->toBe($shouldAllow, "Expected {$from} -> {$to} to be ".($shouldAllow ? 'allowed' : 'rejected'));
        }
    }
});

it('treats closed and cancelled as terminal -- no transition out of either', function () {
    $closed = ServiceRequest::factory()->make(['status' => ServiceRequest::STATUS_CLOSED]);
    $cancelled = ServiceRequest::factory()->make(['status' => ServiceRequest::STATUS_CANCELLED]);

    foreach (array_keys(ServiceRequest::TRANSITIONS) as $status) {
        expect($closed->canTransitionTo($status))->toBeFalse();
        expect($cancelled->canTransitionTo($status))->toBeFalse();
    }
});

// --- Cancellation (previously: allowed by the state machine, but no --
// -- action or endpoint ever triggered it) -------------------------------

it('lets a customer cancel their own request before it starts', function () {
    $customer = Customer::factory()->create();
    $request = ServiceRequest::factory()->create(['customer_id' => $customer->id, 'status' => ServiceRequest::STATUS_NEW]);

    $response = $this->actingAs($customer->user)->postJson("/api/service-requests/{$request->id}/cancel", [
        'reason' => 'No longer needed.',
    ]);

    $response->assertOk()->assertJsonPath('data.status', ServiceRequest::STATUS_CANCELLED);
});

it('cancels an in-progress job and frees the assigned technician', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair'], 'status' => 'on_job']);
    $customer = Customer::factory()->create();
    $request = ServiceRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
    ]);
    app(AssignTechnician::class)->execute($request, $technician);
    app(ScheduleJob::class)->execute($request->fresh(), now()->addDay());

    $response = $this->actingAs($customer->user)->postJson("/api/service-requests/{$request->id}/cancel");

    $response->assertOk()->assertJsonPath('data.status', ServiceRequest::STATUS_CANCELLED);
    expect($technician->fresh()->status)->toBe('available');
});

it('rejects cancelling a request that is already completed', function () {
    $customer = Customer::factory()->create();
    $request = ServiceRequest::factory()->create(['customer_id' => $customer->id, 'status' => ServiceRequest::STATUS_COMPLETED]);

    $this->actingAs($customer->user)
        ->postJson("/api/service-requests/{$request->id}/cancel")
        ->assertStatus(422);
});

it("rejects a customer cancelling another customer's request", function () {
    $owner = Customer::factory()->create();
    $intruder = Customer::factory()->create();
    $request = ServiceRequest::factory()->create(['customer_id' => $owner->id, 'status' => ServiceRequest::STATUS_NEW]);

    $this->actingAs($intruder->user)
        ->postJson("/api/service-requests/{$request->id}/cancel")
        ->assertStatus(403);
});

it('rejects a technician cancelling a request', function () {
    $technician = User::factory()->technician()->create();
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_NEW]);

    $this->actingAs($technician)
        ->postJson("/api/service-requests/{$request->id}/cancel")
        ->assertStatus(403);
});

it('lets an admin cancel any request', function () {
    $admin = User::factory()->admin()->create();
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_ASSIGNED]);

    $this->actingAs($admin)
        ->postJson("/api/service-requests/{$request->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', ServiceRequest::STATUS_CANCELLED);
});
