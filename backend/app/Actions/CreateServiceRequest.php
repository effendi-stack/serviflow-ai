<?php

namespace App\Actions;

use App\Jobs\ProcessServiceRequest as ProcessServiceRequestJob;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\StatusHistory;
use Illuminate\Support\Facades\DB;

/**
 * Persists a new service request as NEW and dispatches the async AI
 * classification job (spec section 4.1/4.3). The HTTP request returns
 * immediately -- the customer never waits on an LLM call.
 */
class CreateServiceRequest
{
    public function execute(Customer $customer, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($customer, $data) {
            $request = ServiceRequest::create([
                'customer_id' => $customer->id,
                'service_type' => $data['service_type'],
                'description' => $data['description'],
                'status' => ServiceRequest::STATUS_NEW,
                'preferred_date' => $data['preferred_date'] ?? null,
                'preferred_time' => $data['preferred_time'] ?? null,
                'address' => $data['address'] ?? $customer->address,
            ]);

            StatusHistory::create([
                'service_request_id' => $request->id,
                'from_status' => null,
                'to_status' => ServiceRequest::STATUS_NEW,
                'note' => 'Request created by customer.',
                'created_at' => now(),
            ]);

            ProcessServiceRequestJob::dispatch($request->id);

            return $request;
        });
    }
}
