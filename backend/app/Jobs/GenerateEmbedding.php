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

    public function __construct(public int $documentId, public array $pages) {}

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
