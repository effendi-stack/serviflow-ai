<?php

use App\Jobs\ProcessServiceRequest;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('lets a customer create a service request and dispatches AI processing', function () {
    Queue::fake();

    $user = User::factory()->customer()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/service-requests', [
        'service_type' => 'Air Conditioning',
        'description' => "AC isn't cold and water is dripping from the indoor unit.",
        'address' => $customer->address,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', ServiceRequest::STATUS_NEW);

    Queue::assertPushed(ProcessServiceRequest::class);
});

it('prevents a customer from viewing another customer\'s request', function () {
    $ownerUser = User::factory()->customer()->create();
    $owner = Customer::factory()->create(['user_id' => $ownerUser->id]);
    $request = ServiceRequest::factory()->create(['customer_id' => $owner->id]);

    $otherUser = User::factory()->customer()->create();
    Customer::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($otherUser)
        ->getJson("/api/service-requests/{$request->id}")
        ->assertStatus(403);
});

it('enforces valid status transitions when starting a job', function () {
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_NEW]);

    expect($request->canTransitionTo(ServiceRequest::STATUS_IN_PROGRESS))->toBeFalse();
    expect($request->canTransitionTo(ServiceRequest::STATUS_ANALYZING))->toBeTrue();
});
