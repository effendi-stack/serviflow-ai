<?php

namespace App\Http\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds a text/event-stream response. The producer receives
 * $send(string $event, mixed $data); each call is written and flushed
 * immediately so the browser renders text as the model generates it.
 *
 * Authorization and validation must happen before this is called: once
 * the stream starts, the HTTP status is already 200.
 */
class ServerSentEvents
{
    public static function response(callable $producer): StreamedResponse
    {
        return response()->stream(function () use ($producer) {
            // flush() pushes each event to the client. This relies on PHP's
            // output_buffering being off (the default in the official PHP
            // Docker image, which ships no php.ini); user-level buffers are
            // left alone so Laravel's test capture keeps working.
            $send = function (string $event, mixed $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
                flush();
            };

            $producer($send);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            // Stops nginx buffering the response until it finishes.
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
