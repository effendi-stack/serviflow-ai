<?php

namespace App\AI;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Contracts\SupportsStreaming;

/**
 * Generates text and reports it to $onToken as it is produced (spec v2
 * section 10.2). With no callback, or a provider that can't stream, it is
 * a plain complete() call -- the full text is still passed to $onToken
 * once, so callers handle both cases the same way.
 */
class AiStream
{
    public static function complete(
        AIProviderInterface $provider,
        string $systemPrompt,
        string $userPrompt,
        array $options,
        ?callable $onToken = null,
    ): string {
        if ($onToken && $provider instanceof SupportsStreaming) {
            return $provider->stream($systemPrompt, $userPrompt, $options, $onToken);
        }

        $text = $provider->complete($systemPrompt, $userPrompt, $options);

        if ($onToken && $text !== '') {
            $onToken($text);
        }

        return $text;
    }
}
