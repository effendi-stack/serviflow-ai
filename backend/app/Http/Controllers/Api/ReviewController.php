<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubmitReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\ServiceRequest;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ReviewController extends Controller
{
    public function store(SubmitReviewRequest $request, ServiceRequest $serviceRequest, SubmitReview $action)
    {
        $this->authorizeCustomerOnJob($serviceRequest);

        try {
            $review = $action->execute(
                $serviceRequest,
                $request->validated('rating'),
                $request->validated('comment'),
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['rating' => [$e->getMessage()]]);
        }

        return (new ReviewResource($review))->response()->setStatusCode(201);
    }
}
