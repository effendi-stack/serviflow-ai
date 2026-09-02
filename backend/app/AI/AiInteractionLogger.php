<?php

namespace App\AI;

use App\AI\Contracts\AIProviderInterface;
use App\Models\AiInteraction;
use Illuminate\Support\Facades\Auth;

/**
 * Every AI call, successful or not, is written to ai_interactions. This is
 * the audit trail described in the spec (section 9.4): it lets us show
 * "here is exactly what the model saw and said" for any recommendation,
 * independent of what the admin ultimately decided.
 */
class AiInteractionLogger
{
    public function log(
        AIProviderInterface $provider,
        string $feature,
        string $input,
        ?string $output,
        ?float $confidence,
        int $latencyMs,
        bool $succeeded,
        ?string $error = null,
    ): AiInteraction {
        return AiInteraction::create([
            'user_id' => Auth::id(),
            'feature' => $feature,
            'model' => $provider->modelIdentifier(),
            'input' => $input,
            'output' => $output,
            'confidence' => $confidence,
            'latency_ms' => $latencyMs,
            'succeeded' => $succeeded,
            'error' => $error,
            'created_at' => now(),
        ]);
    }
}
