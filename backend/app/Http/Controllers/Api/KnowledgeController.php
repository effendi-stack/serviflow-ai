<?php

namespace App\Http\Controllers\Api;

use App\AI\KnowledgeAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\AskKnowledgeRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Support\ServerSentEvents;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KnowledgeController extends Controller
{
    public function uploadDocument(UploadDocumentRequest $request)
    {
        $path = $request->file('file')->store('knowledge-documents');

        $document = Document::create([
            'title' => $request->validated('title'),
            'category' => $request->validated('category'),
            'file_path' => $path,
            'status' => 'uploaded',
            'uploaded_by' => $request->user()->id,
        ]);

        ProcessDocument::dispatch($document->id);

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function documents(Request $request)
    {
        return DocumentResource::collection(
            Document::withCount('chunks')->latest()->paginate($request->integer('per_page', 20))
        );
    }

    /**
     * Admin-only: pulls a document and its indexed chunks out of the
     * knowledge base entirely (roadmap item 16). The underlying file is
     * removed too, so a botched upload doesn't linger in storage.
     */
    public function destroy(Request $request, Document $document)
    {
        abort_unless($request->user()->isAdmin(), 403);

        Storage::delete($document->file_path);
        $document->chunks()->delete();
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    /**
     * Admin-only: re-runs extraction/chunking/embedding from scratch --
     * useful after fixing a parsing issue or bumping the embedding
     * model, without asking the admin to re-upload the file.
     */
    public function reindex(Request $request, Document $document)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $document->chunks()->delete();
        $document->update(['status' => 'uploaded', 'processing_error' => null]);

        ProcessDocument::dispatch($document->id);

        return new DocumentResource($document->fresh());
    }

    public function ask(AskKnowledgeRequest $request, KnowledgeAssistant $assistant)
    {
        $result = $assistant->ask($request->validated('question'));

        return response()->json(['data' => $result]);
    }

    /**
     * Streams the answer as it is generated: "token" events carry text,
     * then "done" carries the final answer and its sources. The final
     * answer replaces the streamed text, which matters if generation
     * failed part-way and a fallback message was returned instead.
     */
    public function askStream(AskKnowledgeRequest $request, KnowledgeAssistant $assistant)
    {
        $question = $request->validated('question');

        return ServerSentEvents::response(function (callable $send) use ($assistant, $question) {
            $result = $assistant->ask($question, fn (string $token) => $send('token', $token));

            $send('done', $result);
        });
    }
}
