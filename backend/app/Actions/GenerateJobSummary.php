<?php

namespace App\Actions;

use App\AI\AiInteractionLogger;
use App\AI\AiStream;
use App\AI\Contracts\AIProviderInterface;
use App\AI\FeatureOptions;
use App\Models\ServiceNote;

/**
 * Turns a technician's raw field notes into a polished service report
 * draft (spec section 4.9). The technician always reviews/edits before
 * anything is submitted -- ai_generated_report and final_report are kept
 * as separate columns precisely so the draft is never silently treated
 * as the final record.
 */
class GenerateJobSummary
{
    public function __construct(
        protected AIProviderInterface $provider,
        protected AiInteractionLogger $logger,
    ) {}

    /**
     * @param  callable(string):void|null  $onToken  receives text as it is generated (streaming endpoint)
     */
    public function execute(ServiceNote $note, ?callable $onToken = null): ServiceNote
    {
        $systemPrompt = <<<'PROMPT'
        You turn a field technician's short notes into a professional,
        customer-facing service report. Keep it factual -- do not add any
        work, parts, or outcomes the technician did not mention. 3-6
        sentences, plain language, past tense. Write only the report
        text: no title, and never placeholders such as [Date] or [Name].
        PROMPT;

        $start = microtime(true);
        $output = null;
        $succeeded = false;
        $error = null;

        try {
            $output = AiStream::complete($this->provider, $systemPrompt, $note->raw_notes, FeatureOptions::for('service_report'), $onToken);
            $succeeded = true;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $output = null;
        } finally {
            $this->logger->log(
                provider: $this->provider,
                feature: 'service_report',
                input: $note->raw_notes,
                output: $output,
                confidence: null,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
                succeeded: $succeeded,
                error: $error,
            );
        }

        $note->update(['ai_generated_report' => $output]);

        return $note;
    }
}
