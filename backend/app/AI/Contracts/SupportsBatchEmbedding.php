<?php

namespace App\AI\Contracts;

/**
 * Optional capability for providers that can embed many texts in one call.
 * Kept separate from AIProviderInterface so simple providers (and test
 * fakes) don't have to implement it; DocumentEmbedder falls back to one
 * embed() call per chunk when a provider doesn't support batching.
 */
interface SupportsBatchEmbedding
{
    /**
     * @param  string[]  $texts
     * @return array<int,float[]> one vector per input, in the same order
     */
    public function embedMany(array $texts): array;

    /**
     * Name of the embedding model, stored with each vector.
     */
    public function embeddingModel(): string;
}
