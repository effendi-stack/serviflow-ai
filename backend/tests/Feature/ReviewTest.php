<?php

use App\Actions\AssignTechnician;
use App\Actions\CompleteJob;
use App\Actions\FinalizeServiceReport;
use App\Actions\ScheduleJob;
use App\Actions\UpsertServiceNote;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Technician;

function completedJob(): array
{
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $customer = Customer::factory()->create();
    $request = ServiceRequest::factory()->create([
        'customer_id' => $customer->id,
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
    ]);

    app(AssignTechnician::class)->execute($request, $technician);
    app(ScheduleJob::class)->execute($request->fresh(), now()->addDay());

    $technicianUser = $technician->user;
    test()->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/start")->assertOk();

    app(UpsertServiceNote::class)->execute($request->fresh(), 'Fixed it.');
    app(FinalizeServiceReport::class)->execute($request->fresh(), 'Replaced the capacitor.');
    app(CompleteJob::class)->execute($request->fresh());

    return [$request->fresh(), $technician, $customer, $customer->user];
}

it('lets the owning customer submit a review and closes the job', function () {
    [$request, , , $customerUser] = completedJob();

    $response = $this->actingAs($customerUser)->postJson("/api/service-requests/{$request->id}/review", [
        'rating' => 5,
        'comment' => 'Great service, on time and tidy.',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.comment', 'Great service, on time and tidy.');

    expect($request->fresh()->status)->toBe(ServiceRequest::STATUS_CLOSED);
});

it('records the technician on the review', function () {
    [$request, $technician, , $customerUser] = completedJob();

    $this->actingAs($customerUser)->postJson("/api/service-requests/{$request->id}/review", ['rating' => 4]);

    expect($request->fresh()->review->technician_id)->toBe($technician->id);
});

it('rejects a review from a customer who does not own the job', function () {
    [$request] = completedJob();
    $intruder = Customer::factory()->create();

    $this->actingAs($intruder->user)
        ->postJson("/api/service-requests/{$request->id}/review", ['rating' => 5])
        ->assertStatus(403);
});

it('rejects reviewing a job before it is completed', function () {
    $customer = Customer::factory()->create();
    $request = ServiceRequest::factory()->create(['customer_id' => $customer->id, 'status' => ServiceRequest::STATUS_IN_PROGRESS]);

    $this->actingAs($customer->user)
        ->postJson("/api/service-requests/{$request->id}/review", ['rating' => 5])
        ->assertStatus(422);
});

it('rejects a second review on the same job', function () {
    [$request, , , $customerUser] = completedJob();

    $this->actingAs($customerUser)->postJson("/api/service-requests/{$request->id}/review", ['rating' => 5]);

    $this->actingAs($customerUser)
        ->postJson("/api/service-requests/{$request->id}/review", ['rating' => 1])
        ->assertStatus(422);
});

it('rejects a rating outside the 1-5 range', function () {
    [$request, , , $customerUser] = completedJob();

    $this->actingAs($customerUser)
        ->postJson("/api/service-requests/{$request->id}/review", ['rating' => 7])
        ->assertStatus(422)
        ->assertJsonValidationErrors('rating');
});

it('rejects a review submitted by a technician', function () {
    [$request, $technician] = completedJob();

    $this->actingAs($technician->user)
        ->postJson("/api/service-requests/{$request->id}/review", ['rating' => 5])
        ->assertStatus(403);
});
