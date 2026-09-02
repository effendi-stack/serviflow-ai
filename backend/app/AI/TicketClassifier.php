<?php

namespace App\AI;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Exceptions\AIProviderException;
use App\AI\Exceptions\AIValidationException;
use App\AI\Schemas\JsonSchemaValidator;
use App\Models\ServiceRequest;

/**
 * Classifies an incoming customer service request: category, priority,
 * likely issue, estimated duration, and the technician skill it needs.
 * This mirrors spec section 4.2 exactly -- Laravel validates the shape
 * before anything is persisted, and the raw model output is kept
 * separately (AiAnalysis::raw_response) from whatever the admin finally
 * approves (ServiceRequest::category/priority/etc).
 */
class TicketClassifier
{
    public const ALLOWED_PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const ALLOWED_CATEGORIES = [
        'air_conditioning', 'plumbing', 'electrical', 'appliance_repair',
        'pest_control', 'general_maintenance', 'other',
    ];

    public function __construct(
        protected AIProviderInterface $provider,
        protected AiInteractionLogger $logger,
    ) {}

    /**
     * @return array{category:string,priority:string,issue:string,estimated_duration_minutes:int,required_skill:string,confidence:float}
     *
     * @throws AIValidationException|AIProviderException
     */
    public function classify(ServiceRequest $request): array
    {
        $systemPrompt = <<<PROMPT
        You are a triage assistant for a field service company. Classify the
        customer's request and respond with ONLY a JSON object, no prose, no
        markdown fences, matching exactly this shape:

        {
          "category": one of {$this->categoryList()},
          "priority": one of {$this->priorityList()},
          "issue": short_snake_case_slug,
          "estimated_duration_minutes": integer between 15 and 480,
          "required_skill": short_snake_case_slug,
          "confidence": float between 0 and 1
        }
        PROMPT;

        $userPrompt = sprintf(
            "Service type: %s\nDescription: %s\nAddress: %s",
            $request->service_type,
            $request->description,
            $request->address,
        );

        $start = microtime(true);
        $raw = null;
        $validated = null;
        $succeeded = false;
        $error = null;

        try {
            $raw = $this->provider->complete($systemPrompt, $userPrompt, ['format' => 'json']);

            $validated = JsonSchemaValidator::validate($raw, [
                'category' => ['type' => 'string', 'enum' => self::ALLOWED_CATEGORIES],
                'priority' => ['type' => 'string', 'enum' => self::ALLOWED_PRIORITIES],
                'issue' => ['type' => 'string'],
                'estimated_duration_minutes' => ['type' => 'int'],
                'required_skill' => ['type' => 'string'],
                'confidence' => ['type' => 'float', 'required' => false],
            ]);

            // Extra deterministic guardrail beyond the enum check: clamp
            // duration into a sane operational range regardless of what the
            // model said.
            $validated['estimated_duration_minutes'] = max(15, min(480, $validated['estimated_duration_minutes']));
            $validated['confidence'] = $validated['confidence'] ?? 0.5;

            $succeeded = true;

            return $validated;
        } catch (AIProviderException|AIValidationException $e) {
            $error = $e->getMessage();

            throw $e;
        } finally {
            $this->logger->log(
                provider: $this->provider,
                feature: 'ticket_classification',
                input: $userPrompt,
                output: $raw,
                confidence: $validated['confidence'] ?? null,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
                succeeded: $succeeded,
                error: $error,
            );
        }
    }

    private function categoryList(): string
    {
        return '['.implode(', ', self::ALLOWED_CATEGORIES).']';
    }

    private function priorityList(): string
    {
        return '['.implode(', ', self::ALLOWED_PRIORITIES).']';
    }
}
