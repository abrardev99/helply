<?php

namespace Database\Factories;

use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'bot_id' => fn (array $attributes) => Document::query()->whereKey($attributes['document_id'])->value('bot_id'),
            'content' => fake()->paragraph(),
            'embedding' => array_map(fn () => fake()->randomFloat(6, -1, 1), range(1, 1536)),
        ];
    }
}
