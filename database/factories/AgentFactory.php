<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'embed_origins' => ['https://'.fake()->unique()->domainName()],
            'status' => AgentStatus::Active,
        ];
    }
}
