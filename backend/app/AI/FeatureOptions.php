<?php

namespace App\AI;

/**
 * Generation settings per AI feature, read from config('ai.features').
 *
 * The returned keys are vendor-neutral: "model_tier" ("fast" or "chat"),
 * "temperature" and "max_tokens". Each provider maps them to its own
 * model names and parameters, so business classes never hard-code a
 * model or an Ollama-specific option.
 */
class FeatureOptions
{
    /**
     * @return array{model_tier:string,temperature:float,max_tokens:int}
     */
    public static function for(string $feature, array $overrides = []): array
    {
        $config = config("ai.features.{$feature}", []);

        return array_merge([
            'model_tier' => $config['model'] ?? 'chat',
            'temperature' => (float) ($config['temperature'] ?? 0.2),
            'max_tokens' => (int) ($config['max_tokens'] ?? 512),
        ], $overrides);
    }
}
