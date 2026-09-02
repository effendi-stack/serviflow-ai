<?php

namespace App\AI\Contracts;

interface AIProviderInterface
{
    /**
     * Send a chat/completion request and get a raw text response back.
     *
     * @param  string  $systemPrompt  Instructions that frame the model's role and output contract.
     * @param  string  $userPrompt  The actual content/question.
     * @param  array  $options  Provider-specific options (e.g. temperature, format).
     * @return string Raw text response from the model.
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string;

    /**
     * Generate an embedding vector for a piece of text.
     *
     * @return float[]
     */
    public function embed(string $text): array;

    /**
     * Identifier used for audit logging (e.g. "ollama:llama3.1").
     */
    public function modelIdentifier(): string;
}
