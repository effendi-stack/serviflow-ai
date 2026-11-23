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

it('uses the fast model, keep_alive and a token cap for short structured tasks', function () {
    Http::fake(['*' => Http::response(['message' => ['content' => '{}']], 200)]);

    $provider = new OllamaProvider(
        baseUrl: 'http://ollama:11434',
        chatModel: 'llama3.1:8b',
        embeddingModel: 'nomic-embed-text',
        fastModel: 'llama3.2:3b',
        keepAlive: '30m',
    );

    $provider->complete('system', 'user', [
        'model_tier' => 'fast', 'temperature' => 0.0, 'max_tokens' => 200, 'format' => 'json',
    ]);

    Http::assertSent(fn ($request) => $request['model'] === 'llama3.2:3b'
        && $request['keep_alive'] === '30m'
        && $request['options']['num_predict'] === 200
        && $request['options']['temperature'] === 0.0
        && $request['format'] === 'json');

    expect($provider->modelIdentifier())->toBe('ollama:llama3.2:3b');
});

it('uses the chat model for long-form tasks and falls back to it when no fast model is set', function () {
    Http::fake(['*' => Http::response(['message' => ['content' => 'ok']], 200)]);

    $provider = new OllamaProvider(
        baseUrl: 'http://ollama:11434',
        chatModel: 'llama3.1:8b',
        embeddingModel: 'nomic-embed-text',
    );

    $provider->complete('system', 'user', ['model_tier' => 'chat']);
    $provider->complete('system', 'user', ['model_tier' => 'fast']);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request['model'] === 'llama3.1:8b' && ! isset($request['options']['num_predict']));
});

it('reads per-feature settings from config so callers never hard-code models', function () {
    config(['ai.features.ticket_classification' => ['model' => 'fast', 'temperature' => 0.0, 'max_tokens' => 200]]);

    expect(\App\AI\FeatureOptions::for('ticket_classification', ['format' => 'json']))->toBe([
        'model_tier' => 'fast', 'temperature' => 0.0, 'max_tokens' => 200, 'format' => 'json',
    ]);
});
