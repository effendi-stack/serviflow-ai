<?php

namespace App\AI;

use Illuminate\Support\Facades\Cache;

/**
 * Caches AI results that are safe to reuse (spec v2 section 10.2):
 * identical inputs to the same model give an equivalent answer, so
 * repeating the call only costs time.
 *
 * Keys include a fingerprint of the AI configuration, so changing a model
 * or provider naturally stops old entries from being served. Only
 * successful results are stored; failures are never cached.
 */
class AiResultCache
{
    public static function key(string $feature, mixed ...$parts): string
    {
        $fingerprint = md5(json_encode([config('ai.provider'), config('ai.ollama'), config("ai.features.{$feature}")]));

        return "ai:{$feature}:".sha1($fingerprint.'|'.json_encode($parts));
    }

    public static function get(string $key): mixed
    {
        return Cache::get($key);
    }

    public static function put(string $key, mixed $value, int $ttlSeconds): void
    {
        Cache::put($key, $value, $ttlSeconds);
    }

    /**
     * For values that never fail validation, such as embeddings.
     */
    public static function remember(string $key, int $ttlSeconds, callable $compute): mixed
    {
        return Cache::remember($key, $ttlSeconds, $compute);
    }
}
