<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Petaling Jaya', 'Kuala Lumpur', 'Shah Alam', 'Subang Jaya']),
            'latitude' => fake()->latitude(2.9, 3.2),
            'longitude' => fake()->longitude(101.4, 101.7),
        ];
    }
}
