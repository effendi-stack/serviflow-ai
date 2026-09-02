<?php

use App\Models\ServiceRequest;
use App\Models\User;

it('calculates the invoice total from labor and parts cost', function () {
    $admin = User::factory()->admin()->create();
    $request = ServiceRequest::factory()->create();

    $response = $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice", [
        'labor_cost' => 120.50,
        'parts_cost' => 45.25,
    ]);

    $response->assertStatus(201)->assertJsonPath('data.total', 165.75);
});

it('prevents a technician from creating an invoice', function () {
    $technician = User::factory()->technician()->create();
    $request = ServiceRequest::factory()->create();

    $this->actingAs($technician)->postJson("/api/service-requests/{$request->id}/invoice", [
        'labor_cost' => 100,
        'parts_cost' => 20,
    ])->assertStatus(403);
});
