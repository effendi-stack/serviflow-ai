<?php

namespace App\AI;

use App\AI\Contracts\AIProviderInterface;

/**
 * Explains dashboard numbers in natural language. The numbers themselves
 * always come from a real Postgres aggregate query (see
 * AnalyticsController) -- this class never invents or adjusts a figure,
 * it only narrates numbers it is handed (spec section 6).
 */
class AnalyticsExplainer
{
    public function __construct(
        protected AIProviderInterface $provider,
        protected AiInteractionLogger $logger,
    ) {}

    public function explain(string $question, array $aggregatedStats): string
    {
        $systemPrompt = <<<'PROMPT'
        You are an operations analyst. You will be given real aggregated
        statistics and a question from a manager. Explain trends and
        possible reasons in plain language. Do not invent any numbers that
        are not present in the data you were given -- if the data doesn't
        support a conclusion, say so.
        PROMPT;

        $userPrompt = "Question: {$question}\n\nData:\n".json_encode($aggregatedStats, JSON_PRETTY_PRINT);

        $start = microtime(true);
        $answer = null;
        $succeeded = false;
        $error = null;

        try {
            $answer = $this->provider->complete($systemPrompt, $userPrompt, FeatureOptions::for('analytics_explanation'));
            $succeeded = true;

            return $answer;
        } catch (\Throwable $e) {
            $error = $e->getMessage();

            return 'AI explanation is temporarily unavailable. The raw statistics above are still accurate.';
        } finally {
            $this->logger->log(
                provider: $this->provider,
                feature: 'analytics_explanation',
                input: $userPrompt,
                output: $answer,
                confidence: null,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
                succeeded: $succeeded,
                error: $error,
            );
        }
    }
}
