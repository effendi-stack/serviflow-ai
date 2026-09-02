<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TechnicianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->technician(),
            'name' => fake()->name(),
            'skills' => fake()->randomElements(
                ['ac_repair', 'plumbing', 'electrical', 'appliance_repair', 'pest_control', 'general_maintenance'],
                fake()->numberBetween(1, 3)
            ),
            'status' => 'available',
            'rating_avg' => fake()->randomFloat(2, 3.5, 5),
            'base_city' => fake()->randomElement(['Petaling Jaya', 'Kuala Lumpur', 'Shah Alam']),
        ];
    }
}
