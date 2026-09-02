<?php

namespace App\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Providers\OllamaProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Binds AIProviderInterface to a concrete provider based on config/ai.php.
 * Swapping to a paid provider later (OpenAI, Anthropic) means adding one
 * class that implements AIProviderInterface and one line here -- nothing
 * in app/AI/*.php or the controllers that use them changes.
 */
class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function () {
            return match (config('ai.provider')) {
                'ollama' => new OllamaProvider(
                    baseUrl: config('ai.ollama.base_url'),
                    chatModel: config('ai.ollama.chat_model'),
                    embeddingModel: config('ai.ollama.embedding_model'),
                    timeout: config('ai.request_timeout'),
                    maxRetries: config('ai.max_retries'),
                ),
                default => throw new \InvalidArgumentException(
                    'Unsupported AI provider: '.config('ai.provider')
                ),
            };
        });
    }
}
