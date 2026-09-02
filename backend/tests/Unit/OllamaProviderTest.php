<?php

use App\AI\Exceptions\AIProviderException;
use App\AI\Providers\OllamaProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('retries once on a transient connection failure and succeeds on the second attempt', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new ConnectionException('Connection timed out.');
        }

        return Http::response(['message' => ['content' => 'hello']], 200);
    });

    $provider = new OllamaProvider(
        baseUrl: 'http://ollama:11434',
        chatModel: 'llama3.1',
        embeddingModel: 'nomic-embed-text',
        timeout: 5,
        maxRetries: 2,
    );

    $result = $provider->complete('system', 'user');

    expect($result)->toBe('hello');
    expect($attempts)->toBe(2);
});

it('gives up after exhausting retries and raises a provider exception for a hard failure', function () {
    Http::fake(['*' => Http::response('server error', 500)]);

    $provider = new OllamaProvider(
        baseUrl: 'http://ollama:11434',
        chatModel: 'llama3.1',
        embeddingModel: 'nomic-embed-text',
        timeout: 5,
        maxRetries: 1,
    );

    expect(fn () => $provider->complete('system', 'user'))
        ->toThrow(AIProviderException::class);
});
