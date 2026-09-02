<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'category' => fake()->randomElement(['manual', 'error_codes', 'sop', 'warranty', 'troubleshooting']),
            'file_path' => 'knowledge-documents/'.fake()->uuid().'.pdf',
            'status' => 'indexed',
            'page_count' => fake()->numberBetween(1, 20),
        ];
    }
}
