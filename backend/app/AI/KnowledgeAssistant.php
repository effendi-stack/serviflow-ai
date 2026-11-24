<?php

namespace App\AI;

use App\AI\Contracts\AIProviderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The RAG assistant technicians query against uploaded manuals/SOPs
 * (spec section 5.3). Retrieval is a pgvector cosine-similarity search
 * done directly in Postgres -- no separate vector database. The model
 * only ever answers from the retrieved chunks, and every answer carries
 * its source document/page back to the caller for traceability.
 */
class KnowledgeAssistant
{
    /** Question embeddings never change for a given model. */
    public const EMBEDDING_CACHE_TTL_SECONDS = 86400;

    /** Answers are keyed on the retrieved chunk ids, so a re-index changes the key. */
    public const ANSWER_CACHE_TTL_SECONDS = 3600;

    public function __construct(
        protected AIProviderInterface $provider,
        protected AiInteractionLogger $logger,
        protected int $topK = 5,
    ) {}

    /**
     * @return array{answer:string, sources:array<int,array{document_id:int,document_title:string,page_number:?int,excerpt:string}>}
     */
    public function ask(string $question): array
    {
        $start = microtime(true);
        $succeeded = false;
        $error = null;
        $answer = null;
        $cacheHit = false;

        try {
            $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $question)));

            $questionEmbedding = AiResultCache::remember(
                AiResultCache::key('question_embedding', $normalized),
                self::EMBEDDING_CACHE_TTL_SECONDS,
                fn () => $this->provider->embed($question),
            );
            $chunks = $this->retrieveSimilarChunks($questionEmbedding);

            if ($chunks->isEmpty()) {
                $answer = "I couldn't find anything in the knowledge base for that question. "
                    .'Try rephrasing, or check whether the relevant manual/SOP has been uploaded.';

                return ['answer' => $answer, 'sources' => []];
            }

            $context = $chunks->map(fn ($c, $i) => sprintf(
                "[Source %d | %s%s]\n%s",
                $i + 1,
                $c->document_title,
                $c->page_number ? " p.{$c->page_number}" : '',
                $c->content,
            ))->implode("\n\n");

            $systemPrompt = <<<'PROMPT'
            You are a field-technician assistant. Answer ONLY using the
            provided sources. If the sources don't contain the answer, say
            so explicitly instead of guessing. Cite sources inline as
            [Source N]. Keep the answer practical and concise.
            PROMPT;

            $userPrompt = "Sources:\n{$context}\n\nQuestion: {$question}";

            $answerKey = AiResultCache::key('knowledge_answer', $normalized, $chunks->pluck('id')->all());
            $answer = AiResultCache::get($answerKey);
            $cacheHit = $answer !== null;

            if (! $cacheHit) {
                $answer = $this->provider->complete($systemPrompt, $userPrompt, FeatureOptions::for('knowledge_answer'));
                AiResultCache::put($answerKey, $answer, self::ANSWER_CACHE_TTL_SECONDS);
            }

            $succeeded = true;

            return [
                'answer' => $answer,
                'sources' => $chunks->map(fn ($c) => [
                    'document_id' => $c->document_id,
                    'document_title' => $c->document_title,
                    'page_number' => $c->page_number,
                    'excerpt' => mb_substr($c->content, 0, 240),
                ])->values()->all(),
            ];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $answer = 'The knowledge assistant is temporarily unavailable. Please try again shortly.';

            return ['answer' => $answer, 'sources' => []];
        } finally {
            $this->logger->log(
                provider: $this->provider,
                feature: 'rag_query',
                input: $question,
                output: $answer,
                confidence: null,
                latencyMs: (int) ((microtime(true) - $start) * 1000),
                succeeded: $succeeded,
                error: $error,
                cacheHit: $cacheHit,
            );
        }
    }

    /**
     * @param  float[]  $embedding
     */
    private function retrieveSimilarChunks(array $embedding): Collection
    {
        $vectorLiteral = '['.implode(',', array_map(fn ($v) => (float) $v, $embedding)).']';

        return collect(DB::select(
            "SELECT document_chunks.id, document_chunks.content, document_chunks.page_number,
                    document_chunks.document_id, documents.title AS document_title,
                    1 - (document_chunks.embedding <=> ?::vector) AS similarity
             FROM document_chunks
             JOIN documents ON documents.id = document_chunks.document_id
             WHERE documents.status = 'indexed'
             ORDER BY document_chunks.embedding <=> ?::vector
             LIMIT ?",
            [$vectorLiteral, $vectorLiteral, $this->topK]
        ));
    }
}
