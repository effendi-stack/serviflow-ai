<?php

use App\Actions\AssignTechnician;
use App\Actions\ScheduleJob;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;

// --- Invoices (roadmap item 20) -----------------------------------------

function draftedInvoiceRequest(): ServiceRequest
{
    $admin = User::factory()->admin()->create();
    $request = ServiceRequest::factory()->create();

    test()->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice", [
        'labor_cost' => 100,
        'parts_cost' => 20,
    ])->assertStatus(201);

    return $request->fresh();
}

it('rejects editing an invoice once it has been issued', function () {
    $admin = User::factory()->admin()->create();
    $request = draftedInvoiceRequest();
    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/issue")->assertOk();

    $this->actingAs($admin)
        ->postJson("/api/service-requests/{$request->id}/invoice", ['labor_cost' => 200, 'parts_cost' => 0])
        ->assertStatus(422);
});

it('rejects issuing a non-draft invoice', function () {
    $admin = User::factory()->admin()->create();
    $request = draftedInvoiceRequest();
    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/issue")->assertOk();

    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/issue")->assertStatus(422);
});

it('rejects marking a draft invoice paid before it is issued', function () {
    $admin = User::factory()->admin()->create();
    $request = draftedInvoiceRequest();

    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/pay")->assertStatus(422);
});

it('cancels a draft invoice', function () {
    $admin = User::factory()->admin()->create();
    $request = draftedInvoiceRequest();

    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('cancels an issued invoice', function () {
    $admin = User::factory()->admin()->create();
    $request = draftedInvoiceRequest();
    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/issue")->assertOk();

    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');
});

it('refuses to cancel a paid invoice', function () {
    $admin = User::factory()->admin()->create();
    $request = draftedInvoiceRequest();
    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/issue")->assertOk();
    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/pay")->assertOk();

    $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/invoice/cancel")->assertStatus(422);
});

it('lists invoice history for admins with status filtering and customer context', function () {
    $admin = User::factory()->admin()->create();
    $paidRequest = draftedInvoiceRequest();
    $this->actingAs($admin)->postJson("/api/service-requests/{$paidRequest->id}/invoice/issue");
    $this->actingAs($admin)->postJson("/api/service-requests/{$paidRequest->id}/invoice/pay");
    draftedInvoiceRequest();

    $response = $this->actingAs($admin)->getJson('/api/invoices?status=paid');

    $response->assertOk();
    $statuses = collect($response->json('data'))->pluck('status');
    expect($statuses->unique()->all())->toBe(['paid']);
    expect(collect($response->json('data'))->pluck('customer_name')->first())->not->toBeNull();
});

it('rejects a non-admin from viewing invoice history', function () {
    $technician = User::factory()->technician()->create();

    $this->actingAs($technician)->getJson('/api/invoices')->assertStatus(403);
});

// --- Technician management (roadmap item 22) -----------------------------

it("includes each technician's current active job count", function () {
    $admin = User::factory()->admin()->create();
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    app(AssignTechnician::class)->execute(
        ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, 'required_skill' => 'ac_repair']),
        $technician,
    );

    $response = $this->actingAs($admin)->getJson('/api/technicians');

    $entry = collect($response->json('data'))->firstWhere('id', $technician->id);
    expect($entry['active_jobs_count'])->toBe(1);
});

it('sets a technician on_job once they start a job and available again once complete', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, 'required_skill' => 'ac_repair']);
    app(AssignTechnician::class)->execute($request, $technician);
    app(ScheduleJob::class)->execute($request->fresh(), now()->addDay());

    $this->actingAs($technician->user)->postJson("/api/service-requests/{$request->id}/start")->assertOk();
    expect($technician->fresh()->status)->toBe('on_job');
});

it('lets an admin take an available technician off duty', function () {
    $admin = User::factory()->admin()->create();
    $technician = Technician::factory()->create(['status' => 'available']);

    $this->actingAs($admin)->patchJson("/api/technicians/{$technician->id}/status", ['status' => 'off_duty'])
        ->assertOk()->assertJsonPath('data.status', 'off_duty');
});

it('refuses to take a technician off duty while they are on a job', function () {
    $admin = User::factory()->admin()->create();
    $technician = Technician::factory()->create(['status' => 'on_job']);

    $this->actingAs($admin)
        ->patchJson("/api/technicians/{$technician->id}/status", ['status' => 'off_duty'])
        ->assertStatus(422);
});

it('rejects a non-admin changing technician status', function () {
    $technician = Technician::factory()->create(['status' => 'available']);
    $requester = User::factory()->technician()->create();

    $this->actingAs($requester)
        ->patchJson("/api/technicians/{$technician->id}/status", ['status' => 'off_duty'])
        ->assertStatus(403);
});

it("shows a technician's recent job history on their detail view", function () {
    $admin = User::factory()->admin()->create();
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, 'required_skill' => 'ac_repair', 'service_type' => 'Air Conditioning']);
    app(AssignTechnician::class)->execute($request, $technician);

    $response = $this->actingAs($admin)->getJson("/api/technicians/{$technician->id}");

    $response->assertOk();
    expect($response->json('data.recent_jobs'))->toHaveCount(1);
    expect($response->json('data.recent_jobs.0.service_request_id'))->toBe($request->id);
});

// --- Customer management (roadmap item 23) -------------------------------

it('searches customers by name, email, or phone', function () {
    $admin = User::factory()->admin()->create();
    $match = Customer::factory()->create(['name' => 'Priya Nair', 'email' => 'priya@example.com']);
    Customer::factory()->create(['name' => 'Someone Else', 'email' => 'other@example.com']);

    $response = $this->actingAs($admin)->getJson('/api/customers?search=priya');

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($match->id);
    expect($ids)->toHaveCount(1);
});

it("shows a customer's request history and count on their detail view", function () {
    $admin = User::factory()->admin()->create();
    $customer = Customer::factory()->create();
    ServiceRequest::factory()->count(3)->create(['customer_id' => $customer->id]);

    $response = $this->actingAs($admin)->getJson("/api/customers/{$customer->id}");

    $response->assertOk();
    expect($response->json('data.service_requests_count'))->toBe(3);
    expect($response->json('data.recent_requests'))->toHaveCount(3);
});
