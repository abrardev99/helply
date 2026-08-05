<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'session_id' => fake()->uuid(),
        ];
    }
}
