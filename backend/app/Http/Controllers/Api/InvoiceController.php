<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Services\Invoicing\InvoiceCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    /**
     * Admin billing overview across every service request (roadmap item
     * 20's "show invoice history") -- the per-request invoice card only
     * ever showed one job's invoice, never a searchable ledger.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $query = Invoice::query()->with('serviceRequest.customer')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return InvoiceResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, ServiceRequest $serviceRequest, InvoiceCalculator $calculator)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($serviceRequest->invoice && $serviceRequest->invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ["This invoice is already {$serviceRequest->invoice->status} and can no longer be edited."],
            ]);
        }

        $data = $request->validate([
            'labor_cost' => ['required', 'numeric', 'min:0'],
            'parts_cost' => ['required', 'numeric', 'min:0'],
            'line_items' => ['sometimes', 'array'],
        ]);

        $invoice = $serviceRequest->invoice()->updateOrCreate(
            ['service_request_id' => $serviceRequest->id],
            [
                'labor_cost' => $data['labor_cost'],
                'parts_cost' => $data['parts_cost'],
                'total' => $calculator->total($data['labor_cost'], $data['parts_cost']),
                'line_items' => $data['line_items'] ?? null,
                'status' => 'draft',
            ]
        );

        return new InvoiceResource($invoice);
    }

    public function issue(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($serviceRequest->invoice, 404);

        if ($serviceRequest->invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ["Only a draft invoice can be issued (current status: {$serviceRequest->invoice->status})."],
            ]);
        }

        $serviceRequest->invoice->update(['status' => 'issued', 'issued_at' => now()]);

        return new InvoiceResource($serviceRequest->invoice->fresh());
    }

    public function pay(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($serviceRequest->invoice, 404);

        if ($serviceRequest->invoice->status !== 'issued') {
            throw ValidationException::withMessages([
                'status' => ["Only an issued invoice can be marked paid (current status: {$serviceRequest->invoice->status})."],
            ]);
        }

        $serviceRequest->invoice->update(['status' => 'paid', 'paid_at' => now()]);

        return new InvoiceResource($serviceRequest->invoice->fresh());
    }

    /**
     * Cancels a draft or issued invoice (roadmap item 20's "cancel where
     * allowed" -- a paid invoice is a completed transaction and cannot
     * be cancelled from here).
     */
    public function cancel(Request $request, ServiceRequest $serviceRequest)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($serviceRequest->invoice, 404);

        if ($serviceRequest->invoice->status === 'paid') {
            throw ValidationException::withMessages([
                'status' => ['A paid invoice cannot be cancelled.'],
            ]);
        }

        $serviceRequest->invoice->update(['status' => 'cancelled']);

        return new InvoiceResource($serviceRequest->invoice->fresh());
    }
}
