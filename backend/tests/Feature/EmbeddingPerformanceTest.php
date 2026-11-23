<?php

use App\AI\Contracts\AIProviderInterface;
use App\AI\Contracts\SupportsBatchEmbedding;
use App\AI\DocumentEmbedder;
use App\AI\Providers\OllamaProvider;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CountingBatchEmbeddingProvider implements AIProviderInterface, SupportsBatchEmbedding
{
    public int $batchCalls = 0;

    public int $singleCalls = 0;

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return '';
    }

    public function embed(string $text): array
    {
        $this->singleCalls++;

        return array_fill(0, 768, 0.1);
    }

    public function embedMany(array $texts): array
    {
        $this->batchCalls++;

        return array_map(fn () => array_fill(0, 768, 0.1), $texts);
    }

    public function embeddingModel(): string
    {
        return 'fake-embed';
    }

    public function modelIdentifier(): string
    {
        return 'fake:batch';
    }
}

function makeEmbeddingTestDocument(): Document
{
    $admin = \App\Models\User::factory()->admin()->create();

    return Document::create([
        'title' => 'AC manual',
        'category' => 'manual',
        'file_path' => 'documents/ac.pdf',
        'status' => 'processing',
        'uploaded_by' => $admin->id,
    ]);
}

it('embeds chunks in batches instead of one request per chunk', function () {
    $provider = new CountingBatchEmbeddingProvider;
    $embedder = new DocumentEmbedder($provider, chunkSize: 100, chunkOverlap: 0);
    $document = makeEmbeddingTestDocument();

    // 70 chunks of 100 chars across two pages -> 3 batches of up to 32.
    $embedder->process($document, [
        ['page_number' => 1, 'text' => str_repeat('a', 100 * 40)],
        ['page_number' => 2, 'text' => str_repeat('b', 100 * 30)],
    ]);

    expect($provider->batchCalls)->toBe(3);
    expect($provider->singleCalls)->toBe(0);

    $chunks = DB::table('document_chunks')->where('document_id', $document->id)->orderBy('chunk_index')->get();

    expect($chunks)->toHaveCount(70);
    expect($chunks->pluck('chunk_index')->all())->toBe(range(0, 69));
    expect($chunks->first()->page_number)->toBe(1);
    expect($chunks->last()->page_number)->toBe(2);
    expect($chunks->every(fn ($c) => $c->embedding !== null && $c->embedding_model === 'fake-embed'))->toBeTrue();
});

it('sends one /api/embed request with an array input for a batch', function () {
    Http::fake(['*/api/embed' => Http::response(['embeddings' => [[0.1, 0.2], [0.3, 0.4]]], 200)]);

    $provider = new OllamaProvider('http://ollama:11434', 'llama3.1:8b', 'nomic-embed-text');

    expect($provider->embedMany(['first', 'second']))->toBe([[0.1, 0.2], [0.3, 0.4]]);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['input'] === ['first', 'second']
        && $request['model'] === 'nomic-embed-text');
});

it('rejects a batch response whose length does not match the input', function () {
    Http::fake(['*/api/embed' => Http::response(['embeddings' => [[0.1]]], 200)]);

    $provider = new OllamaProvider('http://ollama:11434', 'llama3.1:8b', 'nomic-embed-text');

    expect(fn () => $provider->embedMany(['first', 'second']))
        ->toThrow(\App\AI\Exceptions\AIProviderException::class);
});

it('indexes chunk embeddings with HNSW for fast similarity search', function () {
    $index = DB::selectOne(
        "SELECT indexdef FROM pg_indexes WHERE tablename = 'document_chunks' AND indexname = 'document_chunks_embedding_hnsw_idx'"
    );

    expect($index)->not->toBeNull();
    expect($index->indexdef)->toContain('USING hnsw')->toContain('vector_cosine_ops');
});
