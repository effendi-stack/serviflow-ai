<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Contracts\SupportsBatchEmbedding;
use App\AI\Contracts\SupportsStreaming;
use App\AI\Exceptions\AIProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to a local Ollama instance. No API key required -- this keeps the
 * whole AI layer free to run, and is intentionally the only provider that
 * ships enabled by default (see AIProviderInterface / AIServiceProvider).
 *
 * `retry()` here covers transient connection failures (timeouts, refused
 * connections). A non-2xx response is treated as a hard failure rather
 * than retried, since retrying an actual error response (bad model name,
 * malformed request) wouldn't help; that's surfaced as an
 * AIProviderException for the caller to handle (see TicketClassifier /
 * ProcessServiceRequest's fallback-to-manual-triage behavior).
 *
 * Performance (spec v2 section 10.2):
 * - "model_tier" picks a small fast model for short structured tasks and
 *   the larger chat model for long-form text.
 * - "max_tokens" maps to num_predict, capping generation time.
 * - keep_alive keeps models loaded between requests so users don't pay
 *   model-load time after an idle period.
 */
class OllamaProvider implements AIProviderInterface, SupportsBatchEmbedding, SupportsStreaming
{
    protected ?string $lastModel = null;

    public function __construct(
        protected string $baseUrl,
        protected string $chatModel,
        protected string $embeddingModel,
        protected int $timeout = 60,
        protected int $maxRetries = 2,
        protected ?string $fastModel = null,
        protected string $keepAlive = '30m',
    ) {}

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $response = Http::timeout($this->timeout)
            ->retry($this->maxRetries, 200)
            ->baseUrl($this->baseUrl)
            ->post('/api/chat', $this->chatPayload($systemPrompt, $userPrompt, $options, stream: false));

        if ($response->failed()) {
            throw new AIProviderException(
                "Ollama request failed with status {$response->status()}: {$response->body()}"
            );
        }

        return (string) data_get($response->json(), 'message.content', '');
    }

    /**
     * Streams /api/chat. Ollama sends one JSON object per line; each
     * carries a fragment in message.content until one has "done": true.
     * Not retried: once tokens have reached the user, a retry would
     * repeat them.
     */
    public function stream(string $systemPrompt, string $userPrompt, array $options, callable $onToken): string
    {
        $response = Http::timeout($this->timeout)
            ->withOptions(['stream' => true])
            ->baseUrl($this->baseUrl)
            ->post('/api/chat', $this->chatPayload($systemPrompt, $userPrompt, $options, stream: true));

        if ($response->failed()) {
            throw new AIProviderException(
                "Ollama request failed with status {$response->status()}: {$response->body()}"
            );
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $text = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);

                if ($line === '') {
                    continue;
                }

                $chunk = json_decode($line, true);

                if (! is_array($chunk)) {
                    throw new AIProviderException('Ollama sent a malformed stream line.');
                }

                if (isset($chunk['error'])) {
                    throw new AIProviderException("Ollama stream error: {$chunk['error']}");
                }

                $fragment = (string) data_get($chunk, 'message.content', '');

                if ($fragment !== '') {
                    $text .= $fragment;
                    $onToken($fragment);
                }

                if (! empty($chunk['done'])) {
                    return $text;
                }
            }
        }

        if (trim($buffer) !== '') {
            $chunk = json_decode(trim($buffer), true);
            $fragment = (string) data_get($chunk, 'message.content', '');

            if ($fragment !== '') {
                $text .= $fragment;
                $onToken($fragment);
            }
        }

        return $text;
    }

    public function embed(string $text): array
    {
        $response = Http::timeout($this->timeout)
            ->retry($this->maxRetries, 200)
            ->baseUrl($this->baseUrl)
            ->post('/api/embeddings', [
                'model' => $this->embeddingModel,
                'prompt' => $text,
                'keep_alive' => $this->keepAlive,
            ]);

        if ($response->failed()) {
            throw new AIProviderException(
                "Ollama embedding request failed with status {$response->status()}: {$response->body()}"
            );
        }

        return (array) data_get($response->json(), 'embedding', []);
    }

    /**
     * Embed many texts in one request via /api/embed, which accepts an
     * array input -- far fewer round trips than one call per chunk.
     */
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = Http::timeout($this->timeout)
            ->retry($this->maxRetries, 200)
            ->baseUrl($this->baseUrl)
            ->post('/api/embed', [
                'model' => $this->embeddingModel,
                'input' => array_values($texts),
                'keep_alive' => $this->keepAlive,
            ]);

        if ($response->failed()) {
            throw new AIProviderException(
                "Ollama batch embedding request failed with status {$response->status()}: {$response->body()}"
            );
        }

        $embeddings = (array) data_get($response->json(), 'embeddings', []);

        if (count($embeddings) !== count($texts)) {
            throw new AIProviderException(sprintf(
                'Ollama returned %d embeddings for %d inputs.', count($embeddings), count($texts)
            ));
        }

        return $embeddings;
    }

    public function embeddingModel(): string
    {
        return $this->embeddingModel;
    }

    /**
     * Load a model into memory without generating anything, so the first
     * real request doesn't wait for it. An empty prompt with keep_alive is
     * Ollama's documented way to preload a model.
     */
    public function warm(string $model): void
    {
        $isEmbedding = $model === $this->embeddingModel;

        $response = Http::timeout(max($this->timeout, 120))
            ->baseUrl($this->baseUrl)
            ->post($isEmbedding ? '/api/embed' : '/api/generate', $isEmbedding
                ? ['model' => $model, 'input' => '', 'keep_alive' => $this->keepAlive]
                : ['model' => $model, 'keep_alive' => $this->keepAlive]);

        if ($response->failed()) {
            throw new AIProviderException(
                "Ollama could not load model {$model} (status {$response->status()}): {$response->body()}"
            );
        }
    }

    /**
     * @return string[] distinct model names this provider is configured to use
     */
    public function configuredModels(): array
    {
        return array_values(array_unique(array_filter([
            $this->fastModel, $this->chatModel, $this->embeddingModel,
        ])));
    }

    public function modelIdentifier(): string
    {
        return 'ollama:'.($this->lastModel ?? $this->chatModel);
    }

    protected function chatPayload(string $systemPrompt, string $userPrompt, array $options, bool $stream): array
    {
        $model = $this->resolveModel($options);
        $this->lastModel = $model;

        $generation = ['temperature' => $options['temperature'] ?? 0.2];

        if (isset($options['max_tokens'])) {
            $generation['num_predict'] = (int) $options['max_tokens'];
        }

        return [
            'model' => $model,
            'stream' => $stream,
            'keep_alive' => $this->keepAlive,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'options' => $generation,
            // Ask Ollama to constrain output to valid JSON when the caller
            // expects a structured response (see AI/Schemas/*).
            'format' => $options['format'] ?? null,
        ];
    }

    protected function resolveModel(array $options): string
    {
        if (! empty($options['model'])) {
            return $options['model'];
        }

        return ($options['model_tier'] ?? 'chat') === 'fast'
            ? ($this->fastModel ?? $this->chatModel)
            : $this->chatModel;
    }
}
