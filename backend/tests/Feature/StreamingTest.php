<?php

use App\Actions\AssignTechnician;
use App\Actions\ScheduleJob;
use App\AI\Contracts\AIProviderInterface;
use App\AI\Contracts\SupportsStreaming;
use App\AI\DocumentEmbedder;
use App\AI\Exceptions\AIProviderException;
use App\AI\Providers\OllamaProvider;
use App\Models\Document;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Support\Facades\Http;

// Spec v2 section 10.2: long outputs stream to the browser over SSE so
// text appears within a second or two instead of after full generation.

class FakeStreamingProvider implements AIProviderInterface, SupportsStreaming
{
    public bool $fail = false;

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        throw new LogicException('complete() should not be used when streaming');
    }

    public function stream(string $systemPrompt, string $userPrompt, array $options, callable $onToken): string
    {
        $onToken('Replaced ');
        $onToken('the capacitor.');

        if ($this->fail) {
            throw new AIProviderException('connection dropped');
        }

        return 'Replaced the capacitor.';
    }

    public function embed(string $text): array
    {
        return array_fill(0, 768, 0.5);
    }

    public function modelIdentifier(): string
    {
        return 'fake:stream';
    }
}

/**
 * @return array<int,array{event:string,data:mixed}>
 */
function parseSse(string $body): array
{
    preg_match_all("/event: (\\w+)\ndata: (.*)\n\n/", $body, $matches, PREG_SET_ORDER);

    return array_map(fn ($m) => ['event' => $m[1], 'data' => json_decode($m[2], true)], $matches);
}

function jobWithNotes(): array
{
    $technician = Technician::factory()->create(['skills' => ['ac_repair']]);
    $request = ServiceRequest::factory()->create([
        'status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT, 'required_skill' => 'ac_repair',
    ]);
    app(AssignTechnician::class)->execute($request, $technician);
    app(ScheduleJob::class)->execute($request->fresh(), now()->addDay());

    test()->actingAs($technician->user)->postJson("/api/service-requests/{$request->id}/start")->assertOk();
    test()->actingAs($technician->user)->postJson("/api/service-requests/{$request->id}/notes", [
        'raw_notes' => 'Replaced capacitor.',
    ])->assertStatus(201);

    return [$request->fresh(), $technician->user];
}

it('parses Ollama NDJSON stream chunks into tokens', function () {
    Http::fake(['*/api/chat' => Http::response(
        '{"message":{"content":"Hel"},"done":false}'."\n"
        .'{"message":{"content":"lo"},"done":false}'."\n"
        .'{"message":{"content":""},"done":true}'."\n",
        200,
    )]);

    $tokens = [];
    $provider = new OllamaProvider('http://ollama:11434', 'llama3.1:8b', 'nomic-embed-text');
    $text = $provider->stream('system', 'user', ['model_tier' => 'chat'], function ($t) use (&$tokens) {
        $tokens[] = $t;
    });

    expect($tokens)->toBe(['Hel', 'lo']);
    expect($text)->toBe('Hello');
    Http::assertSent(fn ($request) => $request['stream'] === true);
});

it('raises a provider exception when Ollama reports an error mid-stream', function () {
    Http::fake(['*/api/chat' => Http::response('{"error":"model not found"}'."\n", 200)]);

    $provider = new OllamaProvider('http://ollama:11434', 'llama3.1:8b', 'nomic-embed-text');

    expect(fn () => $provider->stream('s', 'u', [], fn () => null))
        ->toThrow(AIProviderException::class);
});

it('streams the service report draft and saves it when finished', function () {
    $this->app->instance(AIProviderInterface::class, new FakeStreamingProvider);
    [$request, $technicianUser] = jobWithNotes();

    $response = $this->actingAs($technicianUser)
        ->post("/api/service-requests/{$request->id}/generate-report/stream");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/event-stream');

    $events = parseSse($response->streamedContent());

    expect(array_column($events, 'event'))->toBe(['token', 'token', 'done']);
    expect($events[2]['data']['ai_generated_report'])->toBe('Replaced the capacitor.');
    expect($request->serviceNote()->first()->ai_generated_report)->toBe('Replaced the capacitor.');
});

it('sends an error event and saves nothing when the stream fails part-way', function () {
    $provider = new FakeStreamingProvider;
    $provider->fail = true;
    $this->app->instance(AIProviderInterface::class, $provider);
    [$request, $technicianUser] = jobWithNotes();

    $events = parseSse($this->actingAs($technicianUser)
        ->post("/api/service-requests/{$request->id}/generate-report/stream")
        ->streamedContent());

    expect(end($events)['event'])->toBe('error');
    expect($request->serviceNote()->first()->ai_generated_report)->toBeNull();
});

it('rejects streaming a report for a job the technician is not assigned to, before streaming starts', function () {
    [$request] = jobWithNotes();
    $intruder = Technician::factory()->create(['skills' => ['ac_repair']]);

    $this->actingAs($intruder->user)
        ->post("/api/service-requests/{$request->id}/generate-report/stream")
        ->assertStatus(403);
});

it('streams a RAG answer and finishes with its sources', function () {
    $provider = new FakeStreamingProvider;
    $this->app->instance(AIProviderInterface::class, $provider);
    $admin = User::factory()->admin()->create();
    $document = Document::create([
        'title' => 'AC manual', 'category' => 'manual', 'file_path' => 'documents/ac.pdf',
        'status' => 'indexed', 'uploaded_by' => $admin->id,
    ]);
    (new DocumentEmbedder($provider, chunkSize: 200, chunkOverlap: 0))
        ->process($document, [['page_number' => 4, 'text' => 'Capacitor replacement steps.']]);

    $events = parseSse($this->actingAs($admin)
        ->post('/api/knowledge/ask/stream', ['question' => 'How do I replace the capacitor?'])
        ->assertOk()
        ->streamedContent());

    expect(array_column($events, 'event'))->toBe(['token', 'token', 'done']);
    expect($events[2]['data']['answer'])->toBe('Replaced the capacitor.');
    expect($events[2]['data']['sources'][0]['page_number'])->toBe(4);
});

it('rejects RAG streaming for customers before streaming starts', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->post('/api/knowledge/ask/stream', ['question' => 'anything'])
        ->assertStatus(403);
});
