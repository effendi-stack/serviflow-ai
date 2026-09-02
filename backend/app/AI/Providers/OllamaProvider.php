<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Exceptions\AIProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to a local Ollama instance. No API key required -- this keeps the
 * whole AI layer free to run, and is intentionally the only provider that
 * ships enabled by default (see AIProviderInterface / AIServiceProvider).
 *
 * `retry()` here covers transient connection failures (timeouts, refused
 * connections) -- config('ai.max_retries') existed before but was never
 * wired to anything. A non-2xx response is treated as a hard failure
 * rather than retried, since retrying an actual error response (bad
 * model name, malformed request) wouldn't help; that's surfaced as an
 * AIProviderException for the caller to handle (see TicketClassifier /
 * ProcessServiceRequest's fallback-to-manual-triage behavior).
 */
class OllamaProvider implements AIProviderInterface
{
    public function __construct(
        protected string $baseUrl,
        protected string $chatModel,
        protected string $embeddingModel,
        protected int $timeout = 60,
        protected int $maxRetries = 2,
    ) {}

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $response = Http::timeout($this->timeout)
            ->retry($this->maxRetries, 200)
            ->baseUrl($this->baseUrl)
            ->post('/api/chat', [
                'model' => $options['model'] ?? $this->chatModel,
                'stream' => false,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'options' => [
                    'temperature' => $options['temperature'] ?? 0.2,
                ],
                // Ask Ollama to constrain output to valid JSON when the caller
                // expects a structured response (see AI/Schemas/*).
                'format' => $options['format'] ?? null,
            ]);

        if ($response->failed()) {
            throw new AIProviderException(
                "Ollama request failed with status {$response->status()}: {$response->body()}"
            );
        }

        return (string) data_get($response->json(), 'message.content', '');
    }

    public function embed(string $text): array
    {
        $response = Http::timeout($this->timeout)
            ->retry($this->maxRetries, 200)
            ->baseUrl($this->baseUrl)
            ->post('/api/embeddings', [
                'model' => $this->embeddingModel,
                'prompt' => $text,
            ]);

        if ($response->failed()) {
            throw new AIProviderException(
                "Ollama embedding request failed with status {$response->status()}: {$response->body()}"
            );
        }

        return (array) data_get($response->json(), 'embedding', []);
    }

    public function modelIdentifier(): string
    {
        return "ollama:{$this->chatModel}";
    }
}
