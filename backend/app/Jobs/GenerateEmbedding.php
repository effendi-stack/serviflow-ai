<?php

namespace App\Jobs;

use App\AI\DocumentEmbedder;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 20;

    /** Large PDFs take minutes; stays below redis-long's retry_after (960s). */
    public int $timeout = 900;

    public function __construct(public int $documentId, public array $pages)
    {
        // Long-running work gets its own queue and a connection with a
        // longer retry_after. Only switch connection when Redis is in use,
        // so the sync driver in tests keeps working.
        if (config('queue.default') === 'redis') {
            $this->onConnection('redis-long');
        }

        $this->onQueue('documents');
    }

    public function handle(DocumentEmbedder $embedder): void
    {
        $document = Document::find($this->documentId);

        if (! $document) {
            return;
        }

        try {
            $embedder->process($document, $this->pages);
            $document->update(['status' => 'indexed']);
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}
