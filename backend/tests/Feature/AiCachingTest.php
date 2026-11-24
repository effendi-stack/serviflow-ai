<?php

use App\AI\Contracts\AIProviderInterface;
use App\AI\DocumentEmbedder;
use App\AI\KnowledgeAssistant;
use App\Models\AiInteraction;
use App\Models\Document;
use App\Models\ServiceRequest;
use App\Models\User;

// Spec v2 section 10.2 / 6: safe-to-reuse AI results and dashboard numbers
// are cached, failures are never cached, and every call -- cached or not --
// still lands in the audit trail.

class CountingCacheProvider implements AIProviderInterface
{
    public int $embedCalls = 0;

    public int $completeCalls = 0;

    public bool $fail = false;

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $this->completeCalls++;

        if ($this->fail) {
            throw new \App\AI\Exceptions\AIProviderException('Ollama is down');
        }

        return 'Check the drain line [Source 1].';
    }

    public function embed(string $text): array
    {
        $this->embedCalls++;

        return array_fill(0, 768, 0.5);
    }

    public function modelIdentifier(): string
    {
        return 'fake:cache';
    }
}

function indexedCacheTestDocument(CountingCacheProvider $provider): void
{
    $admin = User::factory()->admin()->create();
    $document = Document::create([
        'title' => 'AC manual', 'category' => 'manual', 'file_path' => 'documents/ac.pdf',
        'status' => 'indexed', 'uploaded_by' => $admin->id,
    ]);

    (new DocumentEmbedder($provider, chunkSize: 200, chunkOverlap: 0))
        ->process($document, [['page_number' => 3, 'text' => 'E5 means the drain pump is blocked.']]);

    $provider->embedCalls = 0;
}

it('reuses the question embedding and answer for a repeated RAG question', function () {
    $provider = new CountingCacheProvider;
    $this->app->instance(AIProviderInterface::class, $provider);
    indexedCacheTestDocument($provider);

    $assistant = app(KnowledgeAssistant::class);
    $first = $assistant->ask('What does E5 mean?');
    $second = $assistant->ask('  what does   E5 mean? ');

    expect($second)->toBe($first);
    expect($second['sources'][0]['page_number'])->toBe(3);
    expect($provider->embedCalls)->toBe(1);
    expect($provider->completeCalls)->toBe(1);

    $logs = AiInteraction::where('feature', 'rag_query')->orderBy('id')->get();
    expect($logs->pluck('cache_hit')->all())->toBe([false, true]);
});

it('never caches a failed answer', function () {
    $provider = new CountingCacheProvider;
    $this->app->instance(AIProviderInterface::class, $provider);
    indexedCacheTestDocument($provider);

    $provider->fail = true;
    app(KnowledgeAssistant::class)->ask('What does E5 mean?');

    $provider->fail = false;
    $answer = app(KnowledgeAssistant::class)->ask('What does E5 mean?');

    expect($answer['answer'])->toBe('Check the drain line [Source 1].');
    expect($provider->completeCalls)->toBe(2);
});

it('reuses an analytics explanation only while the numbers are unchanged', function () {
    $provider = new CountingCacheProvider;
    $this->app->instance(AIProviderInterface::class, $provider);
    $admin = User::factory()->admin()->create();

    $ask = fn () => $this->actingAs($admin)->postJson('/api/analytics/explain', ['question' => 'Any trends?'])->assertOk();

    $ask();
    $ask();
    expect($provider->completeCalls)->toBe(1);

    ServiceRequest::factory()->create();
    $ask();
    expect($provider->completeCalls)->toBe(2);
});

it('serves the dashboard summary from cache and refreshes it when a request changes', function () {
    $admin = User::factory()->admin()->create();
    ServiceRequest::factory()->count(2)->create();

    $total = fn () => $this->actingAs($admin)->getJson('/api/analytics/summary')->json('data.total_jobs');

    expect($total())->toBe(2);
    expect(cache()->has(\App\Http\Controllers\Api\AnalyticsController::SUMMARY_CACHE_KEY))->toBeTrue();

    ServiceRequest::factory()->create();

    expect($total())->toBe(3);
});
