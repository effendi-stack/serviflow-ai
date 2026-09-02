<?php

use App\Actions\ReviewAiAnalysis;
use App\Models\AiAnalysis;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Services\Technician\EligibilityService;

function readyRequestWithAnalysis(array $analysisOverrides = []): ServiceRequest
{
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT]);

    AiAnalysis::create(array_merge([
        'service_request_id' => $request->id,
        'category' => 'air_conditioning',
        'priority' => 'high',
        'issue' => 'cooling_failure',
        'estimated_duration_minutes' => 90,
        'required_skill' => 'ac_repair',
        'confidence' => 0.87,
        'raw_response' => ['category' => 'air_conditioning'],
    ], $analysisOverrides));

    return $request->fresh();
}

it("accepts the AI suggestion as-is and copies it onto the request's own fields", function () {
    $admin = User::factory()->admin()->create();
    $request = readyRequestWithAnalysis();

    $response = $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/triage", [
        'category' => 'air_conditioning',
        'priority' => 'high',
        'required_skill' => 'ac_repair',
        'estimated_duration_minutes' => 90,
    ]);

    $response->assertOk()->assertJsonPath('data.required_skill', 'ac_repair');
    expect($request->fresh()->aiAnalysis->accepted_by_admin)->toBeTrue();
    expect($request->fresh()->aiAnalysis->reviewed_by)->toBe($admin->id);
    // The AI's own original record is untouched.
    expect($request->fresh()->aiAnalysis->category)->toBe('air_conditioning');
});

it('overrides the AI suggestion and records that it was overridden', function () {
    $admin = User::factory()->admin()->create();
    $request = readyRequestWithAnalysis();

    $response = $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/triage", [
        'category' => 'plumbing',
        'priority' => 'critical',
        'required_skill' => 'plumbing',
        'estimated_duration_minutes' => 120,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.category', 'plumbing')
        ->assertJsonPath('data.required_skill', 'plumbing');

    $fresh = $request->fresh();
    expect($fresh->aiAnalysis->accepted_by_admin)->toBeFalse();
    // The AI's original suggestion is preserved for audit, not overwritten.
    expect($fresh->aiAnalysis->category)->toBe('air_conditioning');
    expect($fresh->aiAnalysis->required_skill)->toBe('ac_repair');
});

it('supports manual triage when the AI never produced an analysis', function () {
    $admin = User::factory()->admin()->create();
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT]);

    $response = $this->actingAs($admin)->postJson("/api/service-requests/{$request->id}/triage", [
        'category' => 'electrical',
        'priority' => 'medium',
        'required_skill' => 'electrical',
        'estimated_duration_minutes' => 60,
    ]);

    $response->assertOk()->assertJsonPath('data.required_skill', 'electrical');
    expect($request->fresh()->aiAnalysis)->toBeNull();
});

it('rejects triage from a non-admin', function () {
    $technician = User::factory()->technician()->create();
    $request = readyRequestWithAnalysis();

    $this->actingAs($technician)
        ->postJson("/api/service-requests/{$request->id}/triage", [
            'category' => 'air_conditioning', 'priority' => 'high', 'required_skill' => 'ac_repair', 'estimated_duration_minutes' => 90,
        ])
        ->assertStatus(403);
});

it('rejects triage once the request is past ready_for_assignment', function () {
    $admin = User::factory()->admin()->create();
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_ASSIGNED]);

    $this->actingAs($admin)
        ->postJson("/api/service-requests/{$request->id}/triage", [
            'category' => 'air_conditioning', 'priority' => 'high', 'required_skill' => 'ac_repair', 'estimated_duration_minutes' => 90,
        ])
        ->assertStatus(422);
});

it('rejects a category outside the allowed enum', function () {
    $admin = User::factory()->admin()->create();
    $request = readyRequestWithAnalysis();

    $this->actingAs($admin)
        ->postJson("/api/service-requests/{$request->id}/triage", [
            'category' => 'not_a_real_category', 'priority' => 'high', 'required_skill' => 'ac_repair', 'estimated_duration_minutes' => 90,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category');
});

it('fixes eligibility filtering once triage sets the required skill', function () {
    $request = ServiceRequest::factory()->create(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT]);
    $acTech = Technician::factory()->create(['skills' => ['ac_repair'], 'status' => 'available']);
    $plumber = Technician::factory()->create(['skills' => ['plumbing'], 'status' => 'available']);

    // Before triage, required_skill is null -- this is the bug the
    // triage step exists to close: every on-duty technician passes the
    // (skipped) skill check.
    $eligibleBefore = app(EligibilityService::class)->eligibleFor($request);
    expect($eligibleBefore->pluck('id'))->toContain($acTech->id, $plumber->id);

    app(ReviewAiAnalysis::class)->execute($request, [
        'category' => 'air_conditioning',
        'priority' => 'high',
        'required_skill' => 'ac_repair',
        'estimated_duration_minutes' => 90,
    ]);

    $eligibleAfter = app(EligibilityService::class)->eligibleFor($request->fresh());
    expect($eligibleAfter->pluck('id'))->toContain($acTech->id);
    expect($eligibleAfter->pluck('id'))->not->toContain($plumber->id);
});
