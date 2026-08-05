<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Agent;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'type' => fake()->randomElement(DocumentType::cases()),
            'source_url' => fake()->url(),
            'title' => fake()->sentence(4),
            'status' => DocumentStatus::Pending,
        ];
    }
}
