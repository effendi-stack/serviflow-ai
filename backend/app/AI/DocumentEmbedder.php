<?php

namespace App\AI;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Contracts\SupportsBatchEmbedding;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Turns extracted document text into embedded, searchable chunks
 * (spec section 5.2). Chunking uses a fixed-size sliding window with
 * overlap so answers retrieved later don't lose context at chunk
 * boundaries. Embeddings are written with raw SQL because pgvector's
 * `vector` type isn't a native Eloquent cast.
 *
 * process() takes per-page text (roadmap item 17) rather than one
 * flattened string -- chunking runs per page, so every chunk carries
 * the real page_number it came from instead of losing that boundary
 * the moment pages get concatenated. A chunk never spans two pages.
 */
class DocumentEmbedder
{
    public function __construct(
        protected AIProviderInterface $provider,
        protected int $chunkSize = 800,
        protected int $chunkOverlap = 120,
    ) {}

    /** Chunks sent to the embedding model per request. */
    public const BATCH_SIZE = 32;

    /**
     * Embeddings are generated before the transaction opens, in batches
     * (spec v2 section 10.4): fewer round trips to Ollama, and no database
     * transaction held open while waiting on the model. Rows are then
     * written in one short transaction, each with its vector and the
     * model that produced it.
     *
     * @param  array<int,array{page_number:?int,text:string}>  $pages
     */
    public function process(Document $document, array $pages): void
    {
        $chunks = [];

        foreach ($pages as $page) {
            foreach ($this->chunk($page['text']) as $chunkText) {
                $chunks[] = ['page_number' => $page['page_number'] ?? null, 'content' => $chunkText];
            }
        }

        $embeddings = $this->embedAll(array_column($chunks, 'content'));
        $model = $this->provider instanceof SupportsBatchEmbedding
            ? $this->provider->embeddingModel()
            : null;
        $now = now();

        DB::transaction(function () use ($document, $chunks, $embeddings, $model, $now) {
            foreach (array_chunk($chunks, 100, true) as $slice) {
                $rows = [];

                foreach ($slice as $index => $chunk) {
                    $rows[] = [
                        'document_id' => $document->id,
                        'chunk_index' => $index,
                        'page_number' => $chunk['page_number'],
                        'content' => $chunk['content'],
                        'embedding_model' => $model,
                        'embedding' => $this->toVectorLiteral($embeddings[$index]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('document_chunks')->insert($rows);
            }
        });
    }

    /**
     * @param  string[]  $texts
     * @return array<int,float[]>
     */
    private function embedAll(array $texts): array
    {
        if (! $this->provider instanceof SupportsBatchEmbedding) {
            return array_map(fn (string $text) => $this->provider->embed($text), $texts);
        }

        $embeddings = [];

        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            array_push($embeddings, ...$this->provider->embedMany($batch));
        }

        return $embeddings;
    }

    /**
     * @return string[]
     */
    private function chunk(string $text): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $length = mb_strlen($text);
        $chunks = [];
        $start = 0;

        while ($start < $length) {
            $end = min($start + $this->chunkSize, $length);
            $chunks[] = mb_substr($text, $start, $end - $start);

            if ($end >= $length) {
                break;
            }

            $start = $end - $this->chunkOverlap;
        }

        return $chunks;
    }

    private function toVectorLiteral(array $embedding): string
    {
        return '['.implode(',', array_map(fn ($v) => (float) $v, $embedding)).']';
    }
}
