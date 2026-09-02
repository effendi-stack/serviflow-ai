<?php

namespace App\AI;

use App\AI\Contracts\AIProviderInterface;
use App\Models\Document;
use App\Models\DocumentChunk;
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

    /**
     * @param  array<int,array{page_number:?int,text:string}>  $pages
     */
    public function process(Document $document, array $pages): void
    {
        DB::transaction(function () use ($document, $pages) {
            $index = 0;

            foreach ($pages as $page) {
                foreach ($this->chunk($page['text']) as $chunkText) {
                    $embedding = $this->provider->embed($chunkText);

                    $chunk = DocumentChunk::create([
                        'document_id' => $document->id,
                        'chunk_index' => $index,
                        'page_number' => $page['page_number'] ?? null,
                        'content' => $chunkText,
                    ]);

                    DB::statement(
                        'UPDATE document_chunks SET embedding = ? WHERE id = ?',
                        [$this->toVectorLiteral($embedding), $chunk->id]
                    );

                    $index++;
                }
            }
        });
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
