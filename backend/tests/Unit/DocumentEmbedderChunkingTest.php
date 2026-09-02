<?php

use App\AI\Contracts\AIProviderInterface;
use App\AI\DocumentEmbedder;

class NullEmbeddingProvider implements AIProviderInterface
{
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return '';
    }

    public function embed(string $text): array
    {
        return array_fill(0, 4, 0.0);
    }

    public function modelIdentifier(): string
    {
        return 'null:test';
    }
}

it('splits long text into overlapping chunks within the configured size', function () {
    $embedder = new DocumentEmbedder(new NullEmbeddingProvider, chunkSize: 100, chunkOverlap: 20);

    $reflection = new ReflectionClass($embedder);
    $method = $reflection->getMethod('chunk');
    $method->setAccessible(true);

    $text = str_repeat('word ', 100); // ~500 chars
    $chunks = $method->invoke($embedder, $text);

    expect($chunks)->not->toBeEmpty();
    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(100);
    }
});
