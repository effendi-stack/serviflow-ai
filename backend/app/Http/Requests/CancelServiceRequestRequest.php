<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // A customer may cancel their own request; technicians have no
        // business reason to cancel one (see roadmap item 11 -- this is
        // an explicit allow-list, not just "any authenticated user").
        $serviceRequest = $this->route('serviceRequest');

        return $user->isCustomer() && $serviceRequest?->customer?->user_id === $user->id;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
