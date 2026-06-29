<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Models\Bot;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $abrar = User::factory()->create([
            'name' => 'Abrar Ahmad',
            'email' => 'abrar.dev99@gmail.com',
            'password' => 'secretsecret',
        ]);

        $bot = Bot::factory()->for($abrar->currentTeam)->create([
            'name' => 'Abrar.pro Assistant',
            'embed_origins' => ['https://www.abrar.pro'],
            'status' => 'active',
        ]);

        Document::factory()->for($bot)->create([
            'type' => 'web',
            'source_url' => 'https://www.abrar.pro/',
            'title' => 'Abrar.pro',
            'status' => DocumentStatus::Done,
        ]);
    }
}
