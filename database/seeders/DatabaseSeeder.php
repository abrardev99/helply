<?php

namespace Database\Seeders;

use App\Enums\AgentStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Agent;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $abrar = User::factory()->create([
            'name' => 'Abrar Ahmad',
            'email' => 'abrar.dev99@gmail.com',
            'password' => Hash::make('password'),
        ]);

        $agent = Agent::factory()->for($abrar->currentTeam)->create([
            'name' => 'Abrar.pro Assistant',
            'embed_origins' => ['https://www.abrar.pro'],
            'status' => AgentStatus::Active,
        ]);

        Document::factory()->for($agent)->create([
            'type' => DocumentType::Web,
            'source_url' => 'https://www.abrar.pro/',
            'title' => 'Abrar.pro',
            'status' => DocumentStatus::Done,
        ]);
    }
}
