<?php

namespace App\AI;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Exceptions\AIProviderException;
use App\AI\Exceptions\AIValidationException;
use App\Models\ServiceRequest;
use App\Models\Technician;
use Illuminate\Support\Collection;

/**
 * Ranks an already-filtered list of eligible technicians. Eligibility
 * itself (skill match, availability, no conflicting job) is decided by
 * App\Services\Technician\EligibilityService using plain deterministic
 * Laravel code -- the AI never sees or filters ineligible technicians,
 * it only explains/orders candidates that are already valid choices
 * (spec section 4.5). If the AI call fails or returns something we can't
 * parse, we fall back to a deterministic ranking (rating, then idle time)
 * so the admin is never blocked from assigning a job.
 */
class TechnicianRecommender
{
    public function __construct(
        protected AIProviderInterface $provider,
        protected AiInteractionLogger $logger,
    ) {}

    /**
     * @param  Collection<int, Technician>  $eligible
     * @return array<int, array{technician_id:int, score:float, rationale:string}>
     */
    public function rank(ServiceRequest $request, Collection $eligible): array
    {
        if ($eligible->isEmpty()) {
            return [];
        }

        $systemPrompt = <<<'PROMPT'
        You are helping a dispatcher pick the best technician for a job from
        a list that is ALREADY filtered to eligible candidates -- do not add,
        remove, or reject anyone from the list. Respond with ONLY a JSON
        array, no prose, no markdown fences, ranked best first, in this
        shape:

        [
          {"technician_id": integer, "score": float between 0 and 100, "rationale": short_string}
        ]

        Every technician_id in your response must be one of the candidate
        ids given to you, and every candidate must appear exactly once.
        PROMPT;

        $userPrompt = json_encode([
            'job' => [
                'category' => $request->category,
                'priority' => $request->priority,
                'required_skill' => $request->required_skill,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
            ],
            'candidates' => $eligible->map(fn (Technician $t) => [
                'technician_id' => $t->id,
                'skills' => $t->skills,
                'rating_avg' => (float) $t->rating_avg,
                'status' => $t->status,
            ])->values(),
        ]);

        $start = microtime(true);
        $raw = null;
        $succeeded = false;
        $error = null;

        try {
            $raw = $this->provider->complete($systemPrompt, $userPrompt, FeatureOptions::for('technician_recommendation', ['format' => 'json']));
            $ranked = $this->validateRanking($raw, $eligible->pluck('id')->all());
            $succeeded = true;

            return $ranked;
        } catch (AIProviderException|AIValidationException $e) {
            $error = $e->getMessage();

            return $this->fallbackRanking($eligible);
        } finally {
            $this->logger->log(
                provider: $this->provider,
                feature: 'technician_recommendation',
                input: $userPrompt,
                output: $raw,
                confidence: null,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
                succeeded: $succeeded,
                error: $error,
            );
        }
    }

    /**
     * @param  int[]  $eligibleIds
     * @return array<int, array{technician_id:int, score:float, rationale:string}>
     */
    private function validateRanking(string $raw, array $eligibleIds): array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(json)?/i', '', $text);
        $text = preg_replace('/```$/', '', $text);
        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded)) {
            throw new AIValidationException('Technician ranking response was not a JSON array.');
        }

        $seen = [];
        $result = [];

        foreach ($decoded as $row) {
            $id = (int) ($row['technician_id'] ?? 0);

            // Reject any id the AI invented that wasn't in the eligible set --
            // this is the enforcement point for "AI ranks, never assigns".
            if (! in_array($id, $eligibleIds, true) || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $result[] = [
                'technician_id' => $id,
                'score' => max(0.0, min(100.0, (float) ($row['score'] ?? 0))),
                'rationale' => (string) ($row['rationale'] ?? ''),
            ];
        }

        if (empty($result)) {
            throw new AIValidationException('Technician ranking response contained no valid candidates.');
        }

        return $result;
    }

    /**
     * @param  Collection<int, Technician>  $eligible
     * @return array<int, array{technician_id:int, score:float, rationale:string}>
     */
    private function fallbackRanking(Collection $eligible): array
    {
        return $eligible
            ->sortByDesc(fn (Technician $t) => (float) $t->rating_avg)
            ->values()
            ->map(fn (Technician $t, int $i) => [
                'technician_id' => $t->id,
                'score' => max(0.0, 80.0 - ($i * 5)),
                'rationale' => 'Deterministic fallback ranking by rating (AI unavailable).',
            ])
            ->all();
    }
}
