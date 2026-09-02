<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_type' => fake()->randomElement(['Air Conditioning', 'Plumbing', 'Electrical']),
            'description' => fake()->sentence(12),
            'status' => ServiceRequest::STATUS_NEW,
            'address' => fake()->streetAddress(),
            'preferred_date' => fake()->dateTimeBetween('now', '+2 weeks'),
        ];
    }
}
