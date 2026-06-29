<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Bot;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'type' => fake()->randomElement(['web', 'pdf']),
            'source_url' => fake()->url(),
            'title' => fake()->sentence(4),
            'status' => DocumentStatus::Pending,
        ];
    }
}
