<?php

namespace App\Jobs;

use App\AI\Exceptions\AIProviderException;
use App\AI\Exceptions\AIValidationException;
use App\AI\TicketClassifier;
use App\Models\AiAnalysis;
use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs AI classification off the request/response cycle (spec section
 * 4.3): HTTP -> save -> dispatch -> Redis -> worker -> Ollama -> validated
 * result. On repeated failure the request is left at READY_FOR_ASSIGNMENT
 * with a null AI analysis so an admin can still triage it manually --
 * the business workflow never gets stuck behind the AI layer.
 */
class ProcessServiceRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public int $serviceRequestId) {}

    public function handle(TicketClassifier $classifier): void
    {
        $request = ServiceRequest::find($this->serviceRequestId);

        if (! $request || $request->status !== ServiceRequest::STATUS_NEW) {
            return;
        }

        $request->update(['status' => ServiceRequest::STATUS_ANALYZING]);
        StatusHistory::create([
            'service_request_id' => $request->id,
            'from_status' => ServiceRequest::STATUS_NEW,
            'to_status' => ServiceRequest::STATUS_ANALYZING,
            'created_at' => now(),
        ]);

        try {
            $result = $classifier->classify($request);

            AiAnalysis::create([
                'service_request_id' => $request->id,
                'category' => $result['category'],
                'priority' => $result['priority'],
                'issue' => $result['issue'],
                'estimated_duration_minutes' => $result['estimated_duration_minutes'],
                'required_skill' => $result['required_skill'],
                'confidence' => $result['confidence'],
                'raw_response' => $result,
            ]);
        } catch (AIProviderException|AIValidationException $e) {
            // Leave category/priority null; admin will classify manually.
            report($e);
        }

        $request->update(['status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT]);
        StatusHistory::create([
            'service_request_id' => $request->id,
            'from_status' => ServiceRequest::STATUS_ANALYZING,
            'to_status' => ServiceRequest::STATUS_READY_FOR_ASSIGNMENT,
            'created_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}
