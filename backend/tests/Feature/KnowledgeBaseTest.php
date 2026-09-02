<?php

use App\AI\AiInteractionLogger;
use App\AI\Contracts\AIProviderInterface;
use App\AI\DocumentEmbedder;
use App\AI\KnowledgeAssistant;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class FixedEmbeddingProvider implements AIProviderInterface
{
    public function __construct(private array $vector) {}

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        return 'Based on the manual, reset the breaker and retest.';
    }

    public function embed(string $text): array
    {
        return $this->vector;
    }

    public function modelIdentifier(): string
    {
        return 'fake:fixed-embedding';
    }
}

/**
 * A 768-dim one-hot vector -- cosine similarity is 1.0 against another
 * one-hot at the same index, and 0.0 (orthogonal) against a different
 * index, which makes retrieval ordering fully deterministic in tests.
 */
function oneHotVector(int $index): array
{
    $vector = array_fill(0, 768, 0.0);
    $vector[$index] = 1.0;

    return $vector;
}

function seedIndexedChunk(Document $document, int $pageNumber, string $content, array $embedding): void
{
    $chunk = $document->chunks()->create([
        'chunk_index' => $document->chunks()->count(),
        'page_number' => $pageNumber,
        'content' => $content,
    ]);

    $literal = '['.implode(',', array_map(fn ($v) => (float) $v, $embedding)).']';
    DB::statement('UPDATE document_chunks SET embedding = ? WHERE id = ?', [$literal, $chunk->id]);
}

// --- Page-aware chunking (roadmap item 17) -----------------------------

it('tags every chunk with the page it actually came from', function () {
    $embedder = new DocumentEmbedder(new FixedEmbeddingProvider(oneHotVector(0)), chunkSize: 500, chunkOverlap: 50);
    $document = Document::factory()->create(['status' => 'processing']);

    $embedder->process($document, [
        ['page_number' => 1, 'text' => str_repeat('alpha ', 200)],
        ['page_number' => 2, 'text' => str_repeat('bravo ', 200)],
    ]);

    $chunks = $document->chunks()->orderBy('chunk_index')->get();

    expect($chunks)->not->toBeEmpty();
    expect($chunks->where('page_number', 1)->pluck('content')->every(fn ($c) => str_contains($c, 'alpha')))->toBeTrue();
    expect($chunks->where('page_number', 2)->pluck('content')->every(fn ($c) => str_contains($c, 'bravo')))->toBeTrue();
    // No chunk mixes text from both pages.
    expect($chunks->contains(fn ($c) => str_contains($c->content, 'alpha') && str_contains($c->content, 'bravo')))->toBeFalse();
    // chunk_index is sequential across the whole document, not reset per page.
    expect($chunks->pluck('chunk_index')->all())->toBe(range(0, $chunks->count() - 1));
});

it('skips blank pages instead of producing empty chunks', function () {
    $embedder = new DocumentEmbedder(new FixedEmbeddingProvider(oneHotVector(0)), chunkSize: 500, chunkOverlap: 50);
    $document = Document::factory()->create(['status' => 'processing']);

    $embedder->process($document, [
        ['page_number' => 1, 'text' => 'Real content on page one.'],
        ['page_number' => 2, 'text' => ''],
    ]);

    expect($document->chunks()->where('page_number', 2)->exists())->toBeFalse();
});

// --- Retrieval (roadmap item 19) ----------------------------------------

it('retrieves the most similar chunk and returns full source metadata', function () {
    $doc = Document::factory()->create(['title' => 'AC Troubleshooting Guide', 'status' => 'indexed']);
    seedIndexedChunk($doc, 3, 'To clear error E4, reset the compressor breaker.', oneHotVector(1));

    $other = Document::factory()->create(['title' => 'Plumbing SOP', 'status' => 'indexed']);
    seedIndexedChunk($other, 1, 'Shut off the main valve before replacing a fixture.', oneHotVector(5));

    // The query embedding is identical to the AC guide's chunk vector,
    // orthogonal to the plumbing one -- so it must win deterministically.
    $assistant = new KnowledgeAssistant(new FixedEmbeddingProvider(oneHotVector(1)), app(AiInteractionLogger::class));

    $result = $assistant->ask('How do I clear error E4?');

    expect($result['sources'])->not->toBeEmpty();
    expect($result['sources'][0]['document_id'])->toBe($doc->id);
    expect($result['sources'][0]['document_title'])->toBe('AC Troubleshooting Guide');
    expect($result['sources'][0]['page_number'])->toBe(3);
    expect($result['sources'][0]['excerpt'])->toContain('reset the compressor breaker');
});

it('only retrieves chunks belonging to indexed documents', function () {
    $failed = Document::factory()->create(['title' => 'Corrupt upload', 'status' => 'failed']);
    seedIndexedChunk($failed, 1, 'This should never surface.', oneHotVector(2));

    $assistant = new KnowledgeAssistant(new FixedEmbeddingProvider(oneHotVector(2)), app(AiInteractionLogger::class));
    $result = $assistant->ask('Anything?');

    expect($result['sources'])->toBeEmpty();
    expect($result['answer'])->toContain("couldn't find anything");
});

it('respects the configured top-k limit', function () {
    $doc = Document::factory()->create(['status' => 'indexed']);
    foreach (range(0, 9) as $i) {
        seedIndexedChunk($doc, 1, "Chunk number {$i}.", oneHotVector($i));
    }

    $assistant = new KnowledgeAssistant(new FixedEmbeddingProvider(oneHotVector(0)), app(AiInteractionLogger::class), topK: 3);
    $result = $assistant->ask('question');

    expect($result['sources'])->toHaveCount(3);
});

it('returns an empty result set gracefully when the knowledge base is empty', function () {
    $assistant = new KnowledgeAssistant(new FixedEmbeddingProvider(oneHotVector(0)), app(AiInteractionLogger::class));

    $result = $assistant->ask('Is anyone there?');

    expect($result['sources'])->toBe([]);
    expect($result['answer'])->toContain("couldn't find anything");
});

// --- Document management (roadmap item 16) ------------------------------

it('lets an admin upload a document and queues processing', function () {
    Queue::fake();
    Storage::fake();
    $admin = User::factory()->admin()->create();

    $file = UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf');

    $response = $this->actingAs($admin)->postJson('/api/knowledge/documents', [
        'title' => 'AC Unit Manual',
        'category' => 'manual',
        'file' => $file,
    ]);

    $response->assertStatus(201)->assertJsonPath('data.title', 'AC Unit Manual')->assertJsonPath('data.status', 'uploaded');
    Queue::assertPushed(ProcessDocument::class);
});

it('rejects a document upload from a non-admin', function () {
    $technician = User::factory()->technician()->create();

    $this->actingAs($technician)
        ->postJson('/api/knowledge/documents', ['title' => 'x', 'category' => 'manual'])
        ->assertStatus(403);
});

it('lets an admin delete a document and its chunks', function () {
    Storage::fake();
    $admin = User::factory()->admin()->create();
    $doc = Document::factory()->create(['file_path' => 'knowledge-documents/test.pdf']);
    seedIndexedChunk($doc, 1, 'content', oneHotVector(0));

    $this->actingAs($admin)->deleteJson("/api/knowledge/documents/{$doc->id}")->assertOk();

    expect(Document::find($doc->id))->toBeNull();
});

it('rejects document deletion from a non-admin', function () {
    $technician = User::factory()->technician()->create();
    $doc = Document::factory()->create();

    $this->actingAs($technician)->deleteJson("/api/knowledge/documents/{$doc->id}")->assertStatus(403);
});

it('lets an admin reindex a document, clearing old chunks and re-queuing processing', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $doc = Document::factory()->create(['status' => 'indexed']);
    seedIndexedChunk($doc, 1, 'stale content', oneHotVector(0));

    $response = $this->actingAs($admin)->postJson("/api/knowledge/documents/{$doc->id}/reindex");

    $response->assertOk()->assertJsonPath('data.status', 'uploaded');
    expect($doc->chunks()->count())->toBe(0);
    Queue::assertPushed(ProcessDocument::class);
});
