<?php

use App\Actions\AssignTechnician;
use App\Actions\ScheduleJob;
use App\AI\Contracts\AIProviderInterface;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;

class FakeReportProvider implements AIProviderInterface
{
    public function __construct(private string $response = 'The technician inspected the unit and replaced the filter.') {}

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return $this->response;
    }

    public function embed(string $text): array
    {
        return array_fill(0, 8, 0.1);
    }

    public function modelIdentifier(): string
    {
        return 'fake:test-model';
    }
}

function inProgressJob(): array
{
    $admin = User::factory()->admin()->create();
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
        'required_skill' => 'ac_repair',
    ]);

    app(AssignTechnician::class)->execute($request, $technician);
    app(ScheduleJob::class)->execute($request->fresh(), now()->addDay());

    $technicianUser = $technician->user;

    test()->actingAs($technicianUser)
        ->postJson("/api/service-requests/{$request->id}/start")
        ->assertOk();

    return [$request->fresh(), $technician, $technicianUser, $admin];
}

it('lets the assigned technician start a scheduled job', function () {
    [$request] = inProgressJob();

    expect($request->status)->toBe(ServiceRequest::STATUS_IN_PROGRESS);
});

it('rejects starting a job by a technician who is not assigned to it', function () {
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $otherTechnician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, 'required_skill' => 'ac_repair']);
    app(AssignTechnician::class)->execute($request, $technician);
    app(ScheduleJob::class)->execute($request->fresh(), now()->addDay());

    $this->actingAs($otherTechnician->user)
        ->postJson("/api/service-requests/{$request->id}/start")
        ->assertStatus(403);
});

it('lets the assigned technician record field notes while the job is in progress', function () {
    [$request, , $technicianUser] = inProgressJob();

    $response = $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/notes", [
        'raw_notes' => 'Replaced capacitor, unit cooling normally now.',
        'photo_paths' => ['photos/before.jpg', 'photos/after.jpg'],
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.raw_notes', 'Replaced capacitor, unit cooling normally now.')
        ->assertJsonPath('data.is_finalized', false)
        ->assertJsonCount(2, 'data.photo_paths');
});

it('rejects notes from a technician who is not assigned to the job', function () {
    [$request] = inProgressJob();
    $intruder = Technician::factory()->create(['skills' => ['ac_repair']]);

    $this->actingAs($intruder->user)
        ->postJson("/api/service-requests/{$request->id}/notes", ['raw_notes' => 'not my job'])
        ->assertStatus(403);
});

it('generates an AI draft report from the recorded notes', function () {
    [$request, , $technicianUser] = inProgressJob();

    $this->app->instance(AIProviderInterface::class, new FakeReportProvider);

    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/notes", [
        'raw_notes' => 'Replaced capacitor, unit cooling normally now.',
    ])->assertStatus(201);

    $response = $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/generate-report");

    $response->assertOk()
        ->assertJsonPath('data.ai_generated_report', 'The technician inspected the unit and replaced the filter.')
        ->assertJsonPath('data.final_report', null);
});

it('rejects generating a report before any notes exist', function () {
    [$request, , $technicianUser] = inProgressJob();

    $this->actingAs($technicianUser)
        ->postJson("/api/service-requests/{$request->id}/generate-report")
        ->assertStatus(422);
});

it('finalizes a report and records who finalized it', function () {
    [$request, , $technicianUser] = inProgressJob();

    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/notes", [
        'raw_notes' => 'Replaced capacitor, unit cooling normally now.',
    ])->assertStatus(201);

    $response = $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/finalize-report", [
        'final_report' => 'Replaced the failed capacitor. Unit tested and cooling normally.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.final_report', 'Replaced the failed capacitor. Unit tested and cooling normally.')
        ->assertJsonPath('data.is_finalized', true);

    expect($request->fresh()->serviceNote->finalized_by)->toBe($technicianUser->id);
});

it('rejects finalizing a report twice', function () {
    [$request, , $technicianUser] = inProgressJob();

    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/notes", ['raw_notes' => 'notes']);
    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/finalize-report", ['final_report' => 'final v1']);

    $this->actingAs($technicianUser)
        ->postJson("/api/service-requests/{$request->id}/finalize-report", ['final_report' => 'final v2'])
        ->assertStatus(422);
});

it('rejects editing notes once the report has been finalized', function () {
    [$request, , $technicianUser] = inProgressJob();

    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/notes", ['raw_notes' => 'notes']);
    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/finalize-report", ['final_report' => 'final']);

    $this->actingAs($technicianUser)
        ->postJson("/api/service-requests/{$request->id}/notes", ['raw_notes' => 'trying to edit after finalization'])
        ->assertStatus(422);
});

it('rejects completing a job before its report is finalized', function () {
    [$request, , $technicianUser] = inProgressJob();

    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/notes", ['raw_notes' => 'notes']);

    $this->actingAs($technicianUser)
        ->postJson("/api/service-requests/{$request->id}/complete")
        ->assertStatus(422);

    expect($request->fresh()->status)->toBe(ServiceRequest::STATUS_IN_PROGRESS);
});

it('completes a job once its report is finalized and frees up the technician', function () {
    [$request, $technician, $technicianUser] = inProgressJob();

    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/notes", ['raw_notes' => 'notes']);
    $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/finalize-report", ['final_report' => 'final report']);

    $response = $this->actingAs($technicianUser)->postJson("/api/service-requests/{$request->id}/complete");

    $response->assertOk()->assertJsonPath('data.status', ServiceRequest::STATUS_COMPLETED);
    expect($technician->fresh()->status)->toBe('available');
});

it("lists a technician's own jobs on the index endpoint, scoped to them", function () {
    [$request, , $technicianUser] = inProgressJob();

    // A job assigned to someone else shouldn't show up.
    $otherTechnician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $other = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, 'required_skill' => 'ac_repair']);
    app(AssignTechnician::class)->execute($other, $otherTechnician);

    $response = $this->actingAs($technicianUser)->getJson('/api/service-requests');

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($request->id);
    expect($ids)->not->toContain($other->id);
});
