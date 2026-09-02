<?php

use App\AI\AiInteractionLogger;
use App\AI\AnalyticsExplainer;
use App\AI\Contracts\AIProviderInterface;
use App\Models\AiInteraction;
use App\Models\ServiceRequest;
use App\Models\User;

class FakeAnalyticsProvider implements AIProviderInterface
{
    public array $seenPrompts = [];

    public function __construct(private string $response = 'Everything looks steady this month.') {}

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $this->seenPrompts[] = $userPrompt;

        return $this->response;
    }

    public function embed(string $text): array
    {
        return array_fill(0, 8, 0.1);
    }

    public function modelIdentifier(): string
    {
        return 'fake:test-model';
    }
}

it('returns summary numbers computed directly from the database, independent of any AI call', function () {
    $admin = User::factory()->admin()->create();
    ServiceRequest::factory()->count(3)->create(['status' => ServiceRequest::STATUS_NEW]);
    ServiceRequest::factory()->count(2)->create(['status' => ServiceRequest::STATUS_CLOSED]);

    $response = $this->actingAs($admin)->getJson('/api/analytics/summary');

    $response->assertOk();
    expect($response->json('data.total_jobs'))->toBe(5);
    expect($response->json('data.by_status.new'))->toBe(3);
    expect($response->json('data.by_status.closed'))->toBe(2);
});

it('hands the AI the exact same aggregated numbers the summary endpoint returns, and never lets it substitute its own', function () {
    $admin = User::factory()->admin()->create();
    ServiceRequest::factory()->count(4)->create(['status' => ServiceRequest::STATUS_NEW]);

    $provider = new FakeAnalyticsProvider('The AI is fabricating a claim about 999 jobs here.');
    $this->app->instance(AIProviderInterface::class, $provider);

    $response = $this->actingAs($admin)->postJson('/api/analytics/explain', [
        'question' => 'How is volume trending?',
    ]);

    $response->assertOk();

    // The stats block returned to the client is the real DB aggregate,
    // never anything the AI said.
    expect($response->json('data.stats.total_jobs'))->toBe(4);

    // The AI was given the real number in its prompt...
    expect($provider->seenPrompts[0])->toContain('"total_jobs": 4');

    // ...but the endpoint doesn't validate or correct the AI's prose --
    // it's surfaced as-is (explanation is advisory narration, not a
    // second source of truth for the numbers). The stats block above is
    // what any consumer should treat as authoritative.
    expect($response->json('data.explanation'))->toBe('The AI is fabricating a claim about 999 jobs here.');
});

it('falls back to a safe message and still returns accurate stats when the AI call fails', function () {
    $admin = User::factory()->admin()->create();
    ServiceRequest::factory()->count(2)->create(['status' => ServiceRequest::STATUS_CLOSED]);

    $failingProvider = new class implements AIProviderInterface
    {
        public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
        {
            throw new RuntimeException('provider unreachable');
        }

        public function embed(string $text): array
        {
            return [];
        }

        public function modelIdentifier(): string
        {
            return 'fake:down';
        }
    };
    $this->app->instance(AIProviderInterface::class, $failingProvider);

    $response = $this->actingAs($admin)->postJson('/api/analytics/explain', ['question' => 'Any trends?']);

    $response->assertOk();
    expect($response->json('data.stats.by_status.closed'))->toBe(2);
    expect($response->json('data.explanation'))->toBe('AI explanation is temporarily unavailable. The raw statistics above are still accurate.');
});

it('logs every analytics AI call to the audit trail', function () {
    $provider = new FakeAnalyticsProvider('narration');

    (new AnalyticsExplainer($provider, app(AiInteractionLogger::class)))
        ->explain('question', ['total_jobs' => 1]);

    expect(AiInteraction::where('feature', 'analytics_explanation')->count())->toBe(1);
});
