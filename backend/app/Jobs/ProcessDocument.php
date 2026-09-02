<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Smalot\PdfParser\Parser;

/**
 * Extracts and cleans text from an uploaded PDF, per page (so page
 * numbers survive into the chunks GenerateEmbedding produces -- see
 * DocumentEmbedder), then dispatches GenerateEmbedding to chunk + embed
 * it (spec section 5.2). Split into two jobs so a slow/failed embedding
 * step can be retried without re-parsing the PDF.
 */
class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(public int $documentId) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (! $document) {
            return;
        }

        $document->update(['status' => 'processing']);

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile(storage_path('app/'.$document->file_path));
            $pdfPages = $pdf->getPages();

            $pages = [];
            foreach ($pdfPages as $i => $pdfPage) {
                $text = $this->clean($pdfPage->getText());

                if ($text !== '') {
                    $pages[] = ['page_number' => $i + 1, 'text' => $text];
                }
            }

            $document->update(['page_count' => count($pdfPages)]);

            GenerateEmbedding::dispatch($document->id, $pages);
        } catch (\Throwable $e) {
            $document->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/\x00/', '', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}
