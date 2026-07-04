<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = fake()->randomElement(MessageRole::cases());

        return [
            'conversation_id' => Conversation::factory(),
            'role' => $role,
            'content' => fake()->paragraph(),
            'sources' => $role === MessageRole::Assistant ? [fake()->url(), fake()->url()] : null,
            'retrieval_score' => $role === MessageRole::Assistant ? fake()->randomFloat(4, 0, 1) : null,
            'flagged' => false,
        ];
    }
}
