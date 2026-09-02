<?php

use App\AI\AiInteractionLogger;
use App\AI\Contracts\AIProviderInterface;
use App\AI\Exceptions\AIValidationException;
use App\AI\TicketClassifier;
use App\Models\AiInteraction;
use App\Models\ServiceRequest;

/**
 * These tests are the enforcement check for spec section 9.3
 * ("AI is untrusted input"): malformed or out-of-range model output must
 * never reach the database as a validated result.
 */
class FakeAIProvider implements AIProviderInterface
{
    public function __construct(private string $response) {}

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

it('accepts a well-formed classification response', function () {
    $provider = new FakeAIProvider(json_encode([
        'category' => 'air_conditioning',
        'priority' => 'high',
        'issue' => 'cooling_failure',
        'estimated_duration_minutes' => 90,
        'required_skill' => 'ac_repair',
        'confidence' => 0.87,
    ]));

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    $result = $classifier->classify($request);

    expect($result['category'])->toBe('air_conditioning');
    expect($result['priority'])->toBe('high');
});

it('rejects a response with a priority outside the allowed enum', function () {
    $provider = new FakeAIProvider(json_encode([
        'category' => 'air_conditioning',
        'priority' => 'urgent-ish', // not in the allowed enum
        'issue' => 'cooling_failure',
        'estimated_duration_minutes' => 90,
        'required_skill' => 'ac_repair',
    ]));

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    expect(fn () => $classifier->classify($request))->toThrow(AIValidationException::class);
});

it('rejects a non-JSON response', function () {
    $provider = new FakeAIProvider('Sure! This looks like a high priority AC issue.');

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    expect(fn () => $classifier->classify($request))->toThrow(AIValidationException::class);
});

it('clamps an out-of-range duration into the allowed operational window', function () {
    $provider = new FakeAIProvider(json_encode([
        'category' => 'air_conditioning',
        'priority' => 'medium',
        'issue' => 'filter_cleaning',
        'estimated_duration_minutes' => 5000, // way outside 15-480
        'required_skill' => 'ac_repair',
    ]));

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    $result = $classifier->classify($request);

    expect($result['estimated_duration_minutes'])->toBe(480);
});

it('rejects a response missing a required field', function () {
    $provider = new FakeAIProvider(json_encode([
        'category' => 'air_conditioning',
        'priority' => 'medium',
        // 'issue' is missing entirely.
        'estimated_duration_minutes' => 60,
        'required_skill' => 'ac_repair',
    ]));

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    expect(fn () => $classifier->classify($request))->toThrow(AIValidationException::class);
});

it('rejects an empty response body', function () {
    $provider = new FakeAIProvider('');

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    expect(fn () => $classifier->classify($request))->toThrow(AIValidationException::class);
});

it('silently drops fields the AI invented that were never in the schema', function () {
    $provider = new FakeAIProvider(json_encode([
        'category' => 'air_conditioning',
        'priority' => 'medium',
        'issue' => 'filter_cleaning',
        'estimated_duration_minutes' => 60,
        'required_skill' => 'ac_repair',
        // Not part of the schema -- must never reach the validated result.
        'admin_override' => true,
        'suggested_price' => 999,
    ]));

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    $result = $classifier->classify($request);

    expect($result)->not->toHaveKey('admin_override');
    expect($result)->not->toHaveKey('suggested_price');
});

it('defaults confidence to 0.5 when the AI omits it, since it is the one optional field', function () {
    $provider = new FakeAIProvider(json_encode([
        'category' => 'air_conditioning',
        'priority' => 'medium',
        'issue' => 'filter_cleaning',
        'estimated_duration_minutes' => 60,
        'required_skill' => 'ac_repair',
        // 'confidence' omitted on purpose.
    ]));

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    $result = $classifier->classify($request);

    expect($result['confidence'])->toBe(0.5);
});

it('logs the failed attempt to the audit trail when validation rejects the response', function () {
    $provider = new FakeAIProvider('not json at all');

    $classifier = new TicketClassifier($provider, app(AiInteractionLogger::class));
    $request = ServiceRequest::factory()->create();

    try {
        $classifier->classify($request);
    } catch (AIValidationException) {
        // expected
    }

    $interaction = AiInteraction::where('feature', 'ticket_classification')->latest('id')->first();
    expect($interaction)->not->toBeNull();
    expect($interaction->succeeded)->toBeFalse();
    expect($interaction->error)->not->toBeNull();
});
