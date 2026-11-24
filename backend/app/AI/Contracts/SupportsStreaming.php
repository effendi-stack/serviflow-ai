<?php

namespace App\AI\Contracts;

/**
 * Optional capability for providers that can stream generated text token
 * by token. Kept separate from AIProviderInterface so simple providers and
 * test fakes don't need it; AiStream falls back to complete().
 */
interface SupportsStreaming
{
    /**
     * @param  callable(string):void  $onToken  called with each text fragment as it arrives
     * @return string the full generated text
     */
    public function stream(string $systemPrompt, string $userPrompt, array $options, callable $onToken): string;
}
