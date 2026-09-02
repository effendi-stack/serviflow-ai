<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        return CustomerResource::collection(
            $query->withCount('serviceRequests')->orderBy('name')->paginate($request->integer('per_page', 20))
        );
    }

    public function show(Customer $customer)
    {
        $customer->loadCount('serviceRequests')
            ->load(['serviceRequests' => fn ($q) => $q->latest()->limit(10)->select('id', 'customer_id', 'service_type', 'status', 'created_at')]);

        return new CustomerResource($customer);
    }
}
